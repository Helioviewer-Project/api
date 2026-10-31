<?php
/**
 * FastMovie: one Helioviewer movie, from its row in the movies table to the
 * finished video files, in one file and in the order it happens.
 *
 * A movie request (Module_Movies::queueMovie / postMovie) stores the request
 * as a row of the movies table and queues a Job_MovieBuilder on the movie
 * queue. A worker picks the job up and calls FastMovie::build($publicId).
 * Everything that happens from then on is in this file, top to bottom:
 *
 *   1. load          the movie row                              (loadMovie)
 *   2. plan          the frame timestamps, the movie's size,    (planMovie)
 *                    frame rate and file name; written back to the row
 *   3. events        every frame's events in one request        (fetchEvents)
 *   4. frames        for every timestamp one BMP                (writeFrame)
 *        a. layers     closest image per layer, decoded with    (buildLayer,
 *                      kdu_expand, coloured, masked, scaled      decodeLayer)
 *                      and placed on the movie's canvas
 *        b. composite  the layers on top of each other
 *        c. overlays   events, movie icons, celestial bodies,   (draw*)
 *                      scale indicator, watermark
 *   5. previews      PNG thumbnails of the middle frame         (writePreviews)
 *   6. encode        hq mp4, mp4, flv and webm with ffmpeg      (encodeMovie)
 *   7. finish        statuses in the database, statistics,     (run)
 *                    frames deleted
 *
 * It produces the same frames and files as the previous code, which was
 * spread over Movie_HelioviewerMovie, Image_Composite_HelioviewerMovieFrame,
 * Image_Composite_HelioviewerCompositeImage, Image_HelioviewerImage,
 * Image_SubFieldImage, the Image_ImageType_* classes and Movie_FFMPEGEncoder.
 * Those classes still serve screenshots and the status API.
 *
 * What it still calls outside this file: the database (Database_ImgIndex,
 * Database_MovieDatabase, Database_Statistics), the request helpers
 * (Helper_HelioviewerLayers, Helper_RegionOfInterest), the events API client
 * (EventContext and EventsApi, FootprintContour, EventRegionSvg,
 * SvgRasterizer), the AIA brightness table (Helper_ImageBrightness), solar
 * rotation (scripts/rot_hpc.php), the celestial bodies module
 * (Module_SolarBodies) and Sentry.
 */
namespace Helioviewer\Api\Movie;

require_once HV_ROOT_DIR . '/../lib/alphaID/alphaID.php';
require_once HV_ROOT_DIR . '/../src/Database/ImgIndex.php';
require_once HV_ROOT_DIR . '/../src/Database/MovieDatabase.php';
require_once HV_ROOT_DIR . '/../src/Database/Statistics.php';
require_once HV_ROOT_DIR . '/../src/Helper/DateTimeConversions.php';
require_once HV_ROOT_DIR . '/../src/Helper/HelioviewerLayers.php';
require_once HV_ROOT_DIR . '/../src/Helper/RegionOfInterest.php';
require_once HV_ROOT_DIR . '/../src/Helper/ImageBrightness.php';
require_once HV_ROOT_DIR . '/../src/Module/SolarBodies.php';
require_once HV_ROOT_DIR . '/../scripts/rot_hpc.php';

use Exception;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Helioviewer\Api\Event\EventContext;
use Helioviewer\Api\Event\Api\EventsApi;
use Helioviewer\Api\Event\Api\EventsApiInterface;
use Helioviewer\Api\Event\FootprintContour;
use Helioviewer\Api\Event\EventRegionSvg;
use Helioviewer\Api\Event\SvgRasterizer;
use Helioviewer\Api\Sentry\Sentry;

class FastMovie
{
    const STATUS_QUEUED     = 0;
    const STATUS_PROCESSING = 1;
    const STATUS_COMPLETED  = 2;
    const STATUS_ERROR      = 3;

    /** A movie is given up after this many frames failed to build. */
    const MAX_FAILED_FRAMES = 3;

    /** The "size" option of a request: 0 keeps the region's own size. */
    const SIZE_PRESETS = [1 => [1280, 720], 2 => [1920, 1080], 3 => [2560, 1440], 4 => [3840, 2160]];

    const FONT = HV_ROOT_DIR . '/../resources/fonts/DejaVuSans.ttf';

    /** Fill colour of an event's region, by event type (hex without #). */
    const EVENT_COLORS = [
        'AR' => 'FF8F97', 'CME' => 'FFB294', 'CD' => 'FFD391', 'CH' => 'FEF38E', 'CW' => 'E8FF8C',
        'FI' => 'C8FF8D', 'FE' => 'A3FF8D', 'FA' => '7BFF8E', 'FL' => '7AFFAE', 'LP' => '7CFFC9',
        'OS' => '81FFFC', 'SS' => '8CE6FF', 'EF' => '95C6FF', 'CJ' => '9DA4FF', 'PG' => 'AB8CFF',
        'OT' => 'CA89FF', 'SG' => 'E986FF', 'SP' => 'FF82FF', 'CR' => 'FF85FF', 'CC' => 'FF8ACC',
        'ER' => 'FF8DAD', 'TO' => 'FF8F97', 'CE' => 'FFB294', 'C3' => 'FFD391', 'FP' => 'AB8CFF',
        'F2' => '7AFFAE', 'BU' => 'E8FF8C', 'EE' => '95C6FF', 'PB' => 'FF85FF', 'PT' => 'C8FF8D',
        'EP' => '81FFFC', 'IC' => '8CE6FF', 'SR' => '9DA4FF', 'HY' => 'CA89FF', 'NR' => 'FFD391',
        'MC' => 'B0C4FF',
    ];

    // ---- the movie row ------------------------------------------------------
    public string $publicId;
    public int    $id;
    public int    $status;
    public string $timestamp;          // when the movie was requested
    public string $reqStartDate;       // the requested time range
    public string $reqEndDate;
    public ?string $reqObservationDate;
    public ?string $startDate;         // the first and last frame's time, once planned
    public ?string $endDate;
    public float  $imageScale;         // arcseconds per pixel
    public float  $frameRate;
    public float  $movieLength;
    public int    $maxFrames;
    public int    $numFrames;
    public int    $width;              // of a frame, in pixels
    public int    $height;
    public int    $size;               // 0, or a SIZE_PRESETS key
    public bool   $watermark;
    public bool   $movieIcons;
    public string $celestialBodiesLabels;
    public string $celestialBodiesTrajectories;
    public bool   $followViewport;
    public bool   $scale;              // draw the scale indicator
    public string $scaleType;          // 'earth' or 'scalebar'
    public float  $scaleX;             // its position, in arcseconds
    public float  $scaleY;
    public bool   $switchSources;      // AIA/HMI requests before SDO's first light fall back to SOHO
    public \Helper_HelioviewerLayers  $layers;
    public \Helper_RegionOfInterest   $roi;
    public array  $eventSelections;
    public array  $eventVisibilitySelections;

    // ---- the build ----------------------------------------------------------
    public string $directory = '';     // <cache>/movies/YYYY/MM/DD/<publicId>/
    public string $filename  = '';     // of the mp4, without directory
    public array  $timestamps = [];    // one per frame, 'YYYY-MM-DD HH:MM:SS'
    public array  $timings = [];       // seconds per step, for the log and the tests
    public bool   $keepFrames = false; // tests: leave the frames on disk

    private \Database_ImgIndex $db;
    private EventsApiInterface $eventsApi;
    private EventContext $events;
    private float $timeOffsetX = 0;    // the viewport's drift in pixels when it follows
    private float $timeOffsetY = 0;    // the Sun's rotation (set per frame by buildLayer)
    private $ffmpegLog = null;

    /**
     * The worker's entry point: builds the movie whose public id this is.
     */
    public static function build(string $publicId): void
    {
        (new self($publicId))->run();
    }

    public function __construct(string $publicId, ?EventsApiInterface $eventsApi = null)
    {
        $this->db        = new \Database_ImgIndex();
        $this->eventsApi = $eventsApi ?? new EventsApi();
        $this->loadMovie($publicId);
    }

    // =========================================================================
    // 1. load: the movie row
    // =========================================================================

    /**
     * Reads the movie's row (movies joined with its mp4 movieFormats row)
     * straight from the database, never from the info cache that the status
     * API keeps.
     */
    private function loadMovie(string $publicId): void
    {
        $id   = (int) alphaID($publicId, true, 5, HV_MOVIE_ID_PASS);
        $info = $this->db->getMovieInformation($id);
        if (empty($info)) {
            throw new Exception('Unable to find the requested movie: ' . $id, 24);
        }

        $this->publicId           = $publicId;
        $this->id                 = $id;
        $this->status             = (int) $info['status'];
        $this->timestamp          = $info['timestamp'];
        $this->reqStartDate       = $info['reqStartDate'];
        $this->reqEndDate         = $info['reqEndDate'];
        $this->reqObservationDate = $info['reqObservationDate'];
        $this->startDate          = $info['startDate'];
        $this->endDate            = $info['endDate'];
        $this->imageScale         = (float) $info['imageScale'];
        $this->frameRate          = (float) $info['frameRate'];
        $this->movieLength        = (float) $info['movieLength'];
        $this->maxFrames          = min((int) $info['maxFrames'], HV_MAX_MOVIE_FRAMES);
        $this->numFrames          = (int) $info['numFrames'];
        $this->width              = (int) $info['width'];
        $this->height             = (int) $info['height'];
        $this->size               = (int) $info['size'];
        $this->watermark          = (bool) $info['watermark'];
        $this->movieIcons         = (bool) $info['movieIcons'];
        $this->celestialBodiesLabels       = (string) $info['celestialBodiesLabels'];
        $this->celestialBodiesTrajectories = (string) $info['celestialBodiesTrajectories'];
        $this->followViewport     = (bool) $info['followViewport'];
        $this->scale              = (bool) $info['scale'];
        $this->scaleType          = (string) $info['scaleType'];
        $this->scaleX             = (float) $info['scaleX'];
        $this->scaleY             = (float) $info['scaleY'];
        $this->switchSources      = isset($info['switchSources']) ? (bool) $info['switchSources'] : false;

        $this->layers = new \Helper_HelioviewerLayers($info['dataSourceString']);
        $this->roi    = \Helper_RegionOfInterest::parsePolygonString($info['roi'], $info['imageScale']);

        // The events the request asked for, as stored by queueMovie / postMovie:
        // { event_selections: [...], event_visibility_selections: {...} }
        $eventsState = json_decode($info['eventsState'], true) ?? [];
        $this->eventSelections           = $eventsState['event_selections'] ?? [];
        $this->eventVisibilitySelections = $eventsState['event_visibility_selections'] ?? [];
    }

    // =========================================================================
    // The build, start to finish
    // =========================================================================

    public function run(): void
    {
        date_default_timezone_set('UTC');

        if ($this->status == self::STATUS_COMPLETED) {
            return;
        }

        $this->db->markMovieAsProcessing($this->id, 'mp4');
        $this->directory = $this->movieDirectory();

        try {
            // A movie whose frames are already on disk (a retried encode)
            // skips straight to the encode.
            if (!@file_exists($this->directory . 'frames')) {
                $buildStart = date('Y-m-d H:i:s');

                $this->planMovie();
                $this->fetchEvents();
                $this->writeFrames();

                $this->db->finishedBuildingMovieFrames($this->id, $buildStart, date('Y-m-d H:i:s'));
            } else {
                $this->filename = $this->movieFilename();
            }
        } catch (Exception $e) {
            Sentry::capture($e);
            $this->abort('Error encountered during movie frame compilation: ' . $e->getFile() . ':' . $e->getLine()
                . ' - ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        $encodeStart = time();
        try {
            $this->encodeMovie();
        } catch (Exception $e) {
            Sentry::capture($e);
            $this->abort('Error encountered during video encoding. This may be caused by an FFmpeg configuration issue, '
                . 'or by insufficient permissions in the cache.' . "\n" . $e->getFile() . ':' . $e->getLine()
                . ' - ' . $e->getMessage() . "\n" . $e->getTraceAsString(), time() - $encodeStart);
        }

        if (HV_ENABLE_STATISTICS_COLLECTION) {
            $statistics = new \Database_Statistics();
            $statistics->log('buildMovie');
            $statistics->logRedis('buildMovie');
        }

        $this->deleteFrames();
    }

    /**
     * Gives the movie up: every format of it is marked as failed, the frames
     * are deleted and the error is thrown to the worker.
     */
    private function abort(string $message, int $procTime = 0): void
    {
        $this->db->markMovieAsInvalid($this->id, $procTime);
        $this->deleteFrames();
        throw new Exception('Unable to create movie ' . $this->publicId . ': ' . $message);
    }

    /** <cache>/movies/YYYY/MM/DD/<publicId>/, from the day the movie was requested. */
    private function movieDirectory(): string
    {
        $date = str_replace('-', '/', substr($this->timestamp, 0, 10));
        return sprintf('%s/movies/%s/%s/', HV_CACHE_DIR, $date, $this->publicId);
    }

    private function log(string $message): void
    {
        error_log(sprintf('[Movie:%s] %s', $this->publicId, $message));
    }

    // =========================================================================
    // 2. plan: timestamps, size, frame rate, file name
    // =========================================================================

    /**
     * Decides what the movie is made of and writes it back to the row:
     * the frame timestamps (and so the real start and end dates), the number
     * of frames, the frame rate or length, the frame size and the file name.
     */
    private function planMovie(): void
    {
        $start = microtime(true);

        $this->timestamps = $this->planTimestamps();
        $this->numFrames  = count($this->timestamps);
        if ($this->numFrames == 0) {
            $this->abort('No images available for the requested time range');
        }
        $this->startDate = $this->timestamps[0];
        $this->endDate   = $this->timestamps[$this->numFrames - 1];
        $this->filename  = $this->movieFilename();

        // The request gives either the frame rate or the length of the movie.
        if ($this->frameRate) {
            $this->movieLength = $this->numFrames / $this->frameRate;
        } else {
            $this->frameRate = min(30, max(1, $this->numFrames / $this->movieLength));
        }

        // The frame size is the region's size in pixels, made even for ffmpeg.
        $this->width  = round($this->roi->getPixelWidth());
        $this->height = round($this->roi->getPixelHeight());
        if ($this->width % 2 === 1) {
            $this->width += 1;
        }
        if ($this->height % 2 === 1) {
            $this->height += 1;
        }

        // The row holds the size of the video files, which is the preset's
        // when one was asked for (the frames are resized to it, see writeFrame).
        [$videoWidth, $videoHeight] = self::SIZE_PRESETS[$this->size] ?? [$this->width, $this->height];
        $this->db->storeMovieProperties(
            $this->id, $this->startDate, $this->endDate, $this->numFrames,
            $this->frameRate, $this->movieLength, $videoWidth, $videoHeight
        );

        Sentry::setContext('Queued Movie', [
            'id' => $this->id, 'frameRate' => $this->frameRate, 'movieLength' => $this->movieLength,
            'maxFrames' => $this->maxFrames, 'numFrames' => $this->numFrames,
            'reqStartDate' => $this->reqStartDate, 'reqEndDate' => $this->reqEndDate,
            'startDate' => $this->startDate, 'endDate' => $this->endDate,
            'width' => $this->width, 'height' => $this->height, 'size' => $this->size,
            'directory' => $this->directory, 'filename' => $this->filename,
        ]);

        $this->timings['plan'] = microtime(true) - $start;
    }

    /**
     * One timestamp per frame, 'YYYY-MM-DD HH:MM:SS', spread evenly over the
     * images of the requested range.
     *
     * The movie may use at most maxFrames images over all its layers, so the
     * frame count is shared out: the layer with the fewest images gets its
     * share first (every frame needs one image of every layer, a layer with
     * few images caps the count), and whatever share is left goes to the
     * next. The frames are then the timestamps of the layer with the most
     * images, sub-sampled to that count.
     */
    private function planTimestamps(): array
    {
        $layerCounts = [];
        foreach ($this->layers->toArray() as $layer) {
            $n = $this->db->getDataCount($this->reqStartDate, $this->reqEndDate, $layer['sourceId'], $this->switchSources);
            if ($n === false) {
                throw new Exception('Failed to query data count for ' . $this->publicId . ' on source ' . $layer['sourceId']);
            }
            $layerCounts[$layer['sourceId']] = $n;
        }
        asort($layerCounts);

        $numFrames       = 0;
        $imagesRemaining = $this->maxFrames;
        $layersRemaining = $this->layers->length();
        foreach ($layerCounts as $sourceId => $count) {
            $numFrames        = min($count, $imagesRemaining / $layersRemaining);
            $imagesRemaining -= $numFrames;
            $layersRemaining -= 1;
        }
        $numFrames = floor($numFrames);

        // $sourceId is now the layer with the most images.
        $entireRange = $this->db->getDataRange($this->reqStartDate, $this->reqEndDate, $sourceId, $this->switchSources);

        $timestamps = [];
        for ($i = 0; $i < $numFrames; $i++) {
            $index        = round($i * (sizeof($entireRange) / $numFrames));
            $timestamps[] = $entireRange[$index]['date'];
        }
        return $timestamps;
    }

    /** "<start>_<end>_<layers>.mp4", with -hq before the extension for the hq file. */
    private function movieFilename(bool $highQuality = false): string
    {
        $start = str_replace([':', '-', ' '], '_', $this->startDate);
        $end   = str_replace([':', '-', ' '], '_', $this->endDate);
        return sprintf('%s_%s_%s%s.mp4', $start, $end, $this->layers->toString(), $highQuality ? '-hq' : '');
    }

    // =========================================================================
    // 3. events: one request for every frame
    // =========================================================================

    /**
     * Asks the events API for the events of every frame at once (the client
     * splits the timestamps into chunks). No selections, no request: the
     * context is then empty and the frames get no events.
     */
    private function fetchEvents(): void
    {
        $start = microtime(true);

        $chunkSize = defined('HV_EVENTS_API_EVENTS_PER_FRAME_CHUNKSIZE') ? (int) HV_EVENTS_API_EVENTS_PER_FRAME_CHUNKSIZE : 50;
        $this->events = EventContext::build(
            frameTimestamps: $this->timestamps,
            selections: $this->eventSelections,
            visibilitySelections: $this->eventVisibilitySelections,
            api: $this->eventsApi,
            chunkSize: $chunkSize,
            logLabel: 'Movie:' . $this->publicId,
        );

        $this->timings['events'] = microtime(true) - $start;
        $this->log(sprintf('Starting movie build, frames=%d, hasEvents=%s, event context built in %dms',
            $this->numFrames, $this->events->hasEvents() ? 'true' : 'false', 1000 * $this->timings['events']));
    }

    // =========================================================================
    // 4. frames
    // =========================================================================

    /**
     * Writes frames/frame0.bmp, frame1.bmp, ... one per timestamp, and the
     * previews from the middle frame. A frame that fails is skipped (and the
     * movie gets one frame shorter) up to MAX_FAILED_FRAMES times.
     */
    private function writeFrames(): void
    {
        // The frames folder exists from the start, so the status API's
        // "current frame" poll never sees it missing.
        $framesDir = $this->directory . 'frames';
        if (!@file_exists($framesDir)) {
            @mkdir($framesDir, 0775, true);
        }

        $previewIndex = floor($this->numFrames / 2);
        $previewFrame = null;
        $frameNumber  = 0;
        $failures     = 0;
        $loopStart    = microtime(true);
        $this->timings['frames'] = [];

        foreach ($this->timestamps as $date) {
            $filepath = sprintf('%sframes/frame%d.bmp', $this->directory, $frameNumber);
            try {
                $frameStart = microtime(true);
                $frame      = $this->writeFrame($date, $filepath);
                $frameMs    = (int) round(1000 * (microtime(true) - $frameStart));
                $this->timings['frames'][] = $frameMs / 1000;

                if ($frameNumber == $previewIndex) {
                    $previewFrame = $frame;
                } else {
                    $frame->destroy();
                }
                $frameNumber++;
                $this->log(sprintf('frame %d/%d (%d%%) in %dms', $frameNumber, $this->numFrames,
                    round(100 * $frameNumber / $this->numFrames), $frameMs));
            } catch (Exception $e) {
                Sentry::capture($e);
                $failures++;
                $this->log(sprintf('frame %d/%d FAILED (failure %d/%d): %s', $frameNumber + 1, $this->numFrames,
                    $failures, self::MAX_FAILED_FRAMES, $e->getMessage()));
                if ($failures > self::MAX_FAILED_FRAMES) {
                    throw $e;
                }
                $this->numFrames--;
            }
        }

        $this->timings['framesTotal'] = microtime(true) - $loopStart;
        $this->log(sprintf('Build complete: %d/%d frames in %dms (event context: %dms, frame loop: %dms, avg %dms/frame, failures: %d)',
            $frameNumber, count($this->timestamps), 1000 * ($this->timings['events'] + $this->timings['framesTotal']),
            1000 * $this->timings['events'], 1000 * $this->timings['framesTotal'],
            $frameNumber ? 1000 * $this->timings['framesTotal'] / $frameNumber : 0, $failures));

        $this->writePreviews($previewFrame);
        $previewFrame->destroy();
    }

    /**
     * One frame: the layers decoded and placed on the canvas, composited
     * bottom to top, the overlays drawn on top, written as a BMP.
     * Returns the frame's image (the caller destroys it).
     */
    private function writeFrame(string $date, string $filepath): Imagick
    {
        set_time_limit(90);   // as the composite image's constructor did, once per frame
        $layers = [];
        foreach ($this->layers->toArray() as $layer) {
            $layers[] = $this->buildLayer($this->switchSource($layer, $date), $date);
        }
        if (empty($layers)) {
            throw new Exception('Unable to create layers needed for composite image', 30);
        }

        // The layers come in drawing order, so each one goes under the next.
        $image = null;
        foreach ($layers as $layer) {
            $previous = $image;
            $image    = $layer['image'];
            if ($previous) {
                $image->compositeImage($previous, Imagick::COMPOSITE_DSTOVER, 0, 0);
                $previous->destroy();
            }
        }

        $this->drawEvents($image, $date);
        if ($this->movieIcons) {
            $this->drawMovieIcons($image, $date);
        }
        if ($this->celestialBodiesTrajectories != '') {
            $this->drawCelestialBodiesTrajectories($image, $date);
        }
        if ($this->celestialBodiesLabels != '') {
            $this->drawCelestialBodiesLabels($image, $date);
        }
        if ($this->scale && $this->scaleType == 'earth') {
            $this->drawEarthScale($image);
        }
        if ($this->scale && $this->scaleType == 'scalebar') {
            $this->drawScaleBar($image);
        }
        if ($this->watermark) {
            $this->drawWatermark($image, $layers);
        }

        // Flatten on black (11 = remove the alpha channel) and write the BMP,
        // resized to the preset when the request asked for one.
        $image->setImageDepth(8);
        $image->commentImage(sprintf('Image created by http://helioviewer.org using data from %s near %s',
            $this->layers->toHumanReadableString(), $date));
        $image->setImageBackgroundColor(new ImagickPixel('black'));
        $image->setImageAlphaChannel(11);
        $image->setImageType(Imagick::IMGTYPE_TRUECOLOR);
        if (isset(self::SIZE_PRESETS[$this->size])) {
            [$w, $h] = self::SIZE_PRESETS[$this->size];
            $image->thumbnailImage($w, $h, true, true);
        }
        $image->writeImage($filepath);

        if (!@file_exists($filepath)) {
            throw new Exception('The requested image is either unavailable or does not exist.', 31);
        }
        return $image;
    }

    /**
     * Before SDO's first light the AIA and HMI layers of a movie that asked
     * for it are taken from SOHO instead: AIA 304 → EIT 304, AIA 171 → EIT 171,
     * AIA 193 → EIT 195, HMI magnetogram → MDI magnetogram, HMI continuum →
     * MDI continuum.
     */
    private function switchSource(array $layer, string $date): array
    {
        if (!$this->switchSources) {
            return $layer;
        }
        $switches = [
            13 => [3, '2010-06-02 00:05:39'],
            10 => [0, '2010-06-02 00:05:36'],
            11 => [1, '2010-06-02 00:05:31'],
            18 => [7, '2010-12-06 06:53:41'],
            19 => [6, '2010-12-06 06:53:41'],
        ];
        if (isset($switches[$layer['sourceId']]) && strtotime($date) < strtotime($switches[$layer['sourceId']][1])) {
            $layer['sourceId'] = $switches[$layer['sourceId']][0];
            $source            = $this->db->getDatasourceInformationFromSourceId($layer['sourceId']);
            $layer['name']     = $source['name'];
            $layer['uiLabels'] = $source['uiLabels'];
        }
        return $layer;
    }

    // -------------------------------------------------------------------------
    // 4a. layers
    // -------------------------------------------------------------------------

    /**
     * The image of one layer at one time: the closest JP2 of the layer's
     * source is found, the offset of the Sun's centre worked out (and moved
     * for a viewport that follows the rotation), the running or base
     * difference image found, and the layer decoded.
     *
     * Returns the layer's image on the movie canvas and its two watermark
     * lines.
     */
    private function buildLayer(array $layer, string $date): array
    {
        $image = $this->db->getClosestData($date, $layer['sourceId']);
        $jp2   = [
            'path'   => HV_JP2_DIR . $image['filepath'] . '/' . $image['filename'],
            'width'  => $image['width'],
            'height' => $image['height'],
            'scale'  => $image['scale'],
        ];

        // Where the centre of the Sun is, relative to the centre of the JP2.
        $offsetX = $image['refPixelX'] - ($image['width'] / 2);
        $offsetY = -($image['refPixelY'] - ($image['height'] / 2));
        $originalOffsetX = $offsetX;
        $originalOffsetY = $offsetY;

        if ($this->followViewport) {
            $timeOffset = $this->sunOffset($this->startDate, $date);

            if ($this->reqObservationDate && $this->inRange($this->reqStartDate, $this->reqEndDate, $this->reqObservationDate)) {
                $timeOffsetStart = $this->sunOffset($this->reqStartDate, $this->reqObservationDate);
                $timeOffset['hv_hpc_x_rot_delta_notscaled'] = -$timeOffsetStart['hv_hpc_x_rot_delta_notscaled'] + $timeOffset['hv_hpc_x_rot_delta_notscaled'];
                $timeOffset['hv_hpc_y_rot_delta_notscaled'] = -$timeOffsetStart['hv_hpc_y_rot_delta_notscaled'] + $timeOffset['hv_hpc_y_rot_delta_notscaled'];
            }

            $x = $timeOffset['hv_hpc_x_rot_delta_notscaled'] * $timeOffset['au_scalar'];
            $y = $timeOffset['hv_hpc_y_rot_delta_notscaled'] * $timeOffset['au_scalar'];

            $this->timeOffsetX = $x / $this->roi->imageScale();
            $this->timeOffsetY = $y / $this->roi->imageScale();

            $multi    = $this->roi->imageScale() / $jp2['scale'];
            $offsetX += ($x * $multi) / $this->roi->imageScale();
            $offsetY += ($y * $multi) / $this->roi->imageScale();
        }

        // A difference layer: the JP2 to subtract, some time (running
        // difference) or a fixed time (base difference) before this one.
        $difference      = null;
        $differenceLabel = '';
        if (isset($layer['difference']) && $layer['difference'] > 0) {
            if ($layer['difference'] == 1) {
                $units   = [6 => 'year', 5 => 'month', 4 => 'week', 3 => 'day', 2 => 'hour', 0 => 'second'];
                $unit    = $units[$layer['diffTime']] ?? 'minute';
                $before  = new \DateTime($image['date']);
                $before->modify('-' . $layer['diffCount'] . ' ' . $unit);
                $dateStr = $before->format("Y-m-d\TH:i:s.000\Z");
                $differenceLabel = ' [RD ' . $layer['diffCount'] . ' ' . $unit . ($layer['diffCount'] > 1 ? 's' : '') . ']';
            } else {
                $dateStr = $layer['baseDiffTime'];
                $differenceLabel = ' [BD ' . (new \DateTime($dateStr))->format('Y-m-d H:i:s') . ']';
            }
            $before     = $this->db->getClosestDataBeforeDate($dateStr, $image['sourceId']);
            $difference = HV_JP2_DIR . $before['filepath'] . '/' . $before['filename'];
        }

        $recipe = $this->layerRecipe($layer['uiLabels'], $image['date']);

        $decoded = $this->decodeLayer($jp2, $recipe, $offsetX, $offsetY,
            $this->followViewport ? $originalOffsetX : $offsetX,
            $this->followViewport ? $originalOffsetY : $offsetY,
            (int) $layer['opacity'], $difference);

        return [
            'image' => $decoded,
            'name'  => trim(join(' ', array_column($layer['uiLabels'], 'name'))) . "\n",
            'date'  => str_replace('T', '   ', $image['date']) . $differenceLabel . "\n",
        ];
    }

    /**
     * What makes each instrument's image look right: the colour table, the
     * brightness correction, the occulter mask and whether the JP2 already
     * carries its own palette. Decided from the layer's label hierarchy, e.g.
     * [SDO, AIA, 304] or [STEREO_A, SECCHI, COR2, white-light].
     */
    private function layerRecipe(array $uiLabels, string $date): array
    {
        $names  = array_column($uiLabels, 'name');
        $tables = HV_ROOT_DIR . '/resources/images/color-tables/';
        $recipe = ['colorTable' => false, 'brightness' => null, 'mask' => null, 'palettedJP2' => false, 'verifyGrayscale' => false];

        if (count($names) >= 3 && $names[1] == 'SECCHI') {
            $instrument = substr($names[2], 0, 3) == 'COR' ? 'COR' : strtoupper($names[2]);
        } elseif ($names[0] == 'TRACE') {
            $instrument = 'TRACE';
        } elseif ($names[0] == 'Hinode') {
            $instrument = 'XRT';
        } elseif ($names[0] == 'RHESSI') {
            $instrument = 'RHESSI';
        } elseif ($names[0] == 'PUNCH') {
            $instrument = 'PUNCH';
        } else {
            $instrument = strtoupper($names[1]);
        }

        switch ($instrument) {
            case 'AIA':
                $wavelength = $names[2] == 'AIA' ? $names[3] : $names[2];
                $recipe['colorTable'] = $tables . 'SDO_AIA_' . $wavelength . '.png';
                // AIA 304 fades over the mission; the correction table brightens it back.
                $recipe['brightness'] = (new \Helper_ImageBrightness($date, $wavelength))->getBrightness();
                break;
            case 'HMI':
            case 'MDI':
            case 'GONG':
                break;                                  // grayscale, no colour table
            case 'EIT':
                $table = $tables . 'SOHO_EIT_' . ($names[3] ?? $names[2]) . '.png';
                if (@file_exists($table)) {
                    $recipe['colorTable'] = $table;
                } else {
                    error_log('Not exist! Label: ' . ($names[3] ?? $names[2]) . '; ColorTable: ' . $table);
                }
                break;
            case 'LASCO':
                $table = $tables . ($names[2] == 'C2' ? 'Red_Temperature.png' : 'Blue_White_Linear.png');
                if (@file_exists($table)) {
                    $recipe['colorTable'] = $table;
                }
                $recipe['mask'] = ['file' => HV_ROOT_DIR . '/resources/images/alpha-masks/LASCO_' . $names[2] . '_Mask.png',
                                   'width' => 1040, 'height' => 1040, 'useOffset' => true, 'blackExtent' => true];
                break;
            case 'COR':
                $table = $tables . ($names[2] == 'COR1' ? 'Green-White_Linear.png' : 'Red_Temperature.png');
                if (@file_exists($table)) {
                    $recipe['colorTable'] = $table;
                }
                $recipe['mask'] = ['file' => sprintf('%s/resources/images/alpha-masks/%s-%s_Mask.png', HV_ROOT_DIR, $names[2], substr($names[0], -1)),
                                   'width' => null, 'height' => null, 'useOffset' => false, 'blackExtent' => true];
                break;
            case 'COSMO':
                $table = $tables . 'kcor_colortable.png';
                if (@file_exists($table)) {
                    $recipe['colorTable'] = $table;
                }
                $recipe['mask'] = ['file' => HV_ROOT_DIR . '/resources/images/alpha-masks/COSMO_KCOR_Mask.png',
                                   'width' => 1040, 'height' => 1040, 'useOffset' => true, 'blackExtent' => true];
                break;
            case 'PUNCH':
                $recipe['colorTable'] = $tables . 'PUNCH.png';
                $recipe['mask'] = ['file' => HV_ROOT_DIR . '/resources/images/alpha-masks/PUNCH_Mask.png',
                                   'width' => 4096, 'height' => 4096, 'useOffset' => true, 'blackExtent' => false, 'centerTransparent' => true];
                break;
            case 'EUVI':
                $recipe['colorTable'] = $tables . 'STEREO_EUVI_' . $names[3] . '.png';
                break;
            case 'EUI':
                $recipe['colorTable'] = $tables . 'EUI_' . $names[3] . '.png';
                break;
            case 'SUVI':
                $recipe['colorTable'] = $tables . 'SUVI_' . $names[2] . '.png';
                break;
            case 'SJI':
                $recipe['colorTable'] = $tables . 'SJI_' . $names[2] . '.png';
                break;
            case 'TRACE':
                $recipe['colorTable'] = $tables . 'TRACE_' . $names[1] . '.png';
                break;
            case 'RHESSI':
                $recipe['colorTable'] = $tables . 'RHESSI.png';
                break;
            case 'XRT':
                $table = $tables . 'Hinode_XRT.png';
                $recipe['colorTable'] = file_exists($table) ? $table : false;
                break;
            case 'SXT':
                $table = $tables . 'Yohkoh_SXT_' . ($names[3] ?? $names[2]) . '.png';
                $recipe['colorTable'] = @file_exists($table) ? $table : false;
                break;
            case 'SWAP':
                // SWAP 174 JP2s carried their own palette until 2013-06-27;
                // that day's images are checked once decoded.
                $imageTime = strtotime($date);
                $table     = $tables . 'PROBA2_SWAP_174.png';
                if ($imageTime < strtotime('2013-06-27 00:00:00')) {
                    $recipe['palettedJP2'] = true;
                } elseif ($imageTime < strtotime('2013-06-27 23:59:59')) {
                    $recipe['verifyGrayscale'] = true;
                }
                if ($recipe['palettedJP2'] === false && @file_exists($table)) {
                    $recipe['colorTable'] = $table;
                } else {
                    $recipe['palettedJP2'] = true;
                }
                break;
            default:
                throw new Exception('No image type for layer ' . join('/', $names), 30);
        }
        return $recipe;
    }

    /**
     * Decodes the part of a JP2 the frame shows and makes it a layer of the
     * frame: the region is cut out with kdu_expand at the nearest power-of-two
     * reduction, coloured through the instrument's colour table (or subtracted
     * from the difference image), masked for a coronagraph, scaled to the
     * frame's resolution and placed on a transparent canvas of the frame's
     * size at the right spot.
     *
     * $offsetX/Y is where the centre of the Sun is relative to the centre of
     * the JP2 (moved for a following viewport); $maskOffsetX/Y the same
     * before the move, which is what the occulter mask wants.
     */
    private function decodeLayer(array $jp2, array $recipe, $offsetX, $offsetY, $maskOffsetX, $maskOffsetY, int $opacity, ?string $difference): Imagick
    {
        // The region of the JP2 to decode, in JP2 pixels.
        $region = $this->roi->getImageSubRegion($jp2['width'], $jp2['height'], $jp2['scale'], $offsetX, $offsetY);

        // How much coarser the frame is than the JP2, and the reduction that
        // gets closest to it without going below.
        $desiredToActual = $this->roi->imageScale() / $jp2['scale'];
        $reduce          = min(max(0, floor(log($desiredToActual, 2))), $this->jp2MaxReduction($jp2['path']));

        $regionWidth  = $region['right'] - $region['left'];
        $regionHeight = $region['bottom'] - $region['top'];
        $frameWidth   = $regionWidth / $desiredToActual;     // the region's size at the frame's resolution
        $frameHeight  = $regionHeight / $desiredToActual;

        // Where the region lands on the canvas (the frame): rounded, as
        // extentImage takes integers.
        $centerX  = $jp2['width'] / 2 + $offsetX;
        $centerY  = $jp2['height'] / 2 + $offsetY;
        $toCanvas = $jp2['scale'] / $this->roi->imageScale();
        $left     = $this->roi->left() / $this->roi->imageScale() - ($region['left'] - $centerX) * $toCanvas;
        $top      = $this->roi->top()  / $this->roi->imageScale() - ($region['top']  - $centerY) * $toCanvas;
        $canvas   = [
            'width'   => round($this->roi->getPixelWidth()),
            'height'  => round($this->roi->getPixelHeight()),
            'offsetX' => ($left < 0.001 && $left > -0.001) ? 0 : round($left),
            'offsetY' => ($top  < 0.001 && $top  > -0.001) ? 0 : round($top),
        ];

        $extension = $recipe['palettedJP2'] ? '.bmp' : '.pgm';
        $input     = $this->directory . 'frames/' . rand() . $extension;
        $this->extractRegion($jp2, $input, $region, $reduce);

        if (!$recipe['palettedJP2']) {
            $image = new Imagick($input);

            $applyColorTable = true;
            if ($difference !== null) {
                $image = $this->subtractDifferenceImage($image, $jp2, $difference, $region, $reduce, $extension);
                $applyColorTable = false;
            }
            if ($recipe['verifyGrayscale'] && $image->getImageType() != Imagick::IMGTYPE_GRAYSCALE) {
                $recipe['colorTable'] = false;
            }

            $image->setImageFormat('PNG');
            $image->setImageDepth(8);
            $image->setImageCompressionQuality(10);

            if ($recipe['colorTable'] && $applyColorTable) {
                if ($recipe['brightness'] !== null) {
                    // Half of the correction as gamma, all of it as brightness.
                    $gammaScale = (($recipe['brightness'] - 1) / 2) + 1;
                    $image->levelImage(0, $gammaScale, 65535);
                    $image->modulateImage($recipe['brightness'] * 100, 100, 100);
                }
                $clut = new Imagick($recipe['colorTable']);
                $image->clutImage($clut);
                $clut->destroy();
            }
        } else {
            $image = new Imagick($input);
            if ($difference !== null) {
                $image = $this->subtractDifferenceImage($image, $jp2, $difference, $region, $reduce, $extension);
            }
        }

        // Transparency: a coronagraph is cut out with its occulter mask,
        // everything else just gets its opacity.
        if ($recipe['mask'] !== null) {
            $this->clipOcculter($image, $recipe['mask'], $jp2, $region, $reduce, $maskOffsetX, $maskOffsetY, $regionWidth, $regionHeight, $opacity);
        } else {
            $this->setOpacity($image, $opacity);
        }
        $image->setImageDepth(8);

        // To the frame's resolution, then onto the canvas.
        $image->scaleImage(round($frameWidth), round($frameHeight));
        $image->setImageBackgroundColor('transparent');
        $image->extentImage($canvas['width'], $canvas['height'], $canvas['offsetX'], $canvas['offsetY']);

        if (@file_exists($input)) {
            @unlink($input);
        }
        return $image;
    }

    /**
     * Runs kdu_expand to decode $region of the JP2 into $outputFile (a PGM, or
     * a BMP for a paletted JP2), at 1/2^$reduce of its resolution. Retried
     * twice; kdu_expand prints at most 6 lines when it succeeds.
     */
    private function extractRegion(array $jp2, string $outputFile, array $region, $reduce): void
    {
        $cmd = HV_KDU_EXPAND . ' -i ' . $jp2['path'] . ' -o ' . $outputFile . ' ';
        if ($reduce > 0) {
            $cmd .= '-reduce ' . $reduce . ' ';
        }

        // The region as fractions of the image: {top,left},{height,width},
        // 6 characters each, never negative.
        $fraction = function ($value) {
            return substr($value > 0.0001 ? $value : 0, 0, 6);
        };
        $cmd .= sprintf('-region {%s,%s},{%s,%s}',
            $fraction($region['top'] / $jp2['height']), $fraction($region['left'] / $jp2['width']),
            $fraction(($region['bottom'] - $region['top']) / $jp2['height']),
            $fraction(($region['right'] - $region['left']) / $jp2['width']));

        set_time_limit(600);   // as the subfield image did before each decode

        for ($attempt = 0; $attempt < 3; $attempt++) {
            exec(escapeshellcmd($cmd) . ' 2>&1', $out, $ret);
            if ($ret == 0 && sizeof($out) <= 6) {
                return;
            }
            usleep(200000);
        }
        throw new Exception(sprintf("Error extracting JPEG 2000 subfield region!\n\nCOMMAND:\n%s\n\nRETURN VALUE:%d\n\nOUTPUT:\n%s",
            escapeshellcmd($cmd), $ret, implode("\n", $out)), 14);
    }

    /**
     * How many times a JP2 can be halved (its number of wavelet levels), read
     * from the file's header by kdu_expand without decoding it.
     */
    private function jp2MaxReduction(string $path): int
    {
        $record = tempnam('/tmp', 'kdu_');
        exec(escapeshellcmd(HV_KDU_EXPAND . ' -no_decode -record ' . $record . ' -i ' . $path) . ' 2>&1', $out, $ret);
        $metadata = file_get_contents($record);
        if (preg_match('/Clevels=(\d+)/', $metadata, $matches) != 1) {
            $msg = "Couldn't determine the number of DWT levels in " . $path;
            error_log($msg . ' tmpfile is ' . $record);
            throw new Exception($msg, 14);
        }
        unlink($record);
        return (int) $matches[1];
    }

    /**
     * A difference layer: the same region of the earlier JP2 is decoded and
     * subtracted pixel by pixel; no change is mid-grey, a change of ±64 or
     * more is black or white.
     */
    private function subtractDifferenceImage(Imagick $image, array $jp2, string $differencePath, array $region, $reduce, string $extension): Imagick
    {
        ini_set('memory_limit', '8096M');
        ini_set('max_execution_time', '0');

        $input = $this->directory . 'frames/' . rand() . $extension;
        $this->extractRegion(['path' => $differencePath] + $jp2, $input, $region, $reduce);
        $before = new Imagick($input);

        $width  = $image->getImageWidth();
        $height = $image->getImageHeight();
        $pixelsBefore = $before->exportImagePixels(0, 0, $width, $height, 'I', Imagick::PIXEL_CHAR);
        $pixels       = $image->exportImagePixels(0, 0, $width, $height, 'I', Imagick::PIXEL_CHAR);
        foreach ($pixels as $k => $v) {
            if ($pixels[$k] != $pixelsBefore[$k]) {
                $color = max(-64, min(63, $pixels[$k] - $pixelsBefore[$k]));
                $pixels[$k] = ($color + 64) * 2;
            } else {
                $pixels[$k] = 128;
            }
        }

        $result = new Imagick();
        $result->newImage($width, $height, 'gray');
        $result->importImagePixels(0, 0, $width, $height, 'I', Imagick::PIXEL_CHAR, $pixels);

        $before->destroy();
        $image->destroy();
        if (@file_exists($input)) {
            @unlink($input);
        }
        return $result;
    }

    /** The layer's opacity, 0 to 100 (setImageOpacity is deprecated but still what ImageMagick 6 wants). */
    private function setOpacity(Imagick $image, int $opacity): void
    {
        try {
            @$image->setImageOpacity($opacity / 100);
        } catch (\Throwable $e) {
            $image->setImageAlpha($opacity / 100);
        }
    }

    /**
     * Cuts the occulter and the corners out of a coronagraph layer with the
     * instrument's alpha mask: the part of the mask that covers the decoded
     * region is cut out at the same reduction and copied into the layer's
     * alpha channel.
     *
     * The LASCO, COSMO and PUNCH masks are centred on the Sun ("useOffset":
     * the mask moves with the Sun's offset in the JP2) and are 1040 or 4096
     * pixels; the COR mask is the size of the JP2 and centred on it. When the
     * region reaches past the mask's edge, the mask is cropped at the edge
     * and padded back with black ("blackExtent"; PUNCH keeps its own size).
     * PUNCH also makes the exact-black pixels inside the occulter transparent.
     */
    private function clipOcculter(Imagick $image, array $mask, array $jp2, array $region, $reduce, $offsetX, $offsetY, $regionWidth, $regionHeight, int $opacity): void
    {
        $maskWidth  = $mask['width']  ?? $jp2['width'];
        $maskHeight = $mask['height'] ?? $jp2['height'];
        $scale      = $reduce > 0 ? 1 / pow(2, $reduce) : 1;

        $maskLeft = ($region['left'] + ($maskWidth  - $jp2['width'])  / 2 - ($mask['useOffset'] ? $offsetX : 0)) * $scale;
        $maskTop  = ($region['top']  + ($maskHeight - $jp2['height']) / 2 - ($mask['useOffset'] ? $offsetY : 0)) * $scale;
        $width    = $regionWidth  * $scale;
        $height   = $regionHeight * $scale;

        // A region that starts before the mask's edge: crop from the edge and
        // put the cropped mask back at the right spot.
        $cropWidth  = round($width  + min($maskLeft, 0));
        $cropHeight = round($height + min($maskTop, 0));
        $maskLeft   = round($maskLeft);
        $maskTop    = round($maskTop);
        $width      = round($width);
        $height     = round($height);

        $alpha = new Imagick($mask['file']);
        $alpha->scaleImage($maskWidth * $scale, $maskHeight * $scale);
        $alpha->cropImage($cropWidth, $cropHeight, max($maskLeft, 0), max($maskTop, 0));
        $alpha->resetImagePage($mask['useOffset'] ? $width . 'x' . $height . '+0+0' : '{' . $width . '}x{' . $height . '}+0+0');
        if ($mask['blackExtent']) {
            $alpha->setImageBackgroundColor('black');
            $alpha->extentImage($width, $height, $width - $cropWidth, $height - $cropHeight);
        }

        $image->setImageExtent($width, $height);
        $image->compositeImage($alpha, Imagick::COMPOSITE_COPYOPACITY, 0, 0);

        if ($opacity < 100) {
            $alpha->negateImage(true);
            @$image->setImageClipMask($alpha);
            $this->setOpacity($image, $opacity);
        }
        $alpha->destroy();

        if (!empty($mask['centerTransparent'])) {
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);
            $image->transparentPaintImage(new ImagickPixel('black'), 0.0, 0, false);
        }
    }

    /**
     * How far the centre of the region has rotated between two times, in
     * arcseconds (not yet scaled for the Earth's distance), for a viewport
     * that follows the rotation.
     */
    private function sunOffset(string $startDate, string $date): array
    {
        $startTime = str_replace(' ', 'T', $startDate) . '.000Z';
        $imageTime = str_replace(' ', 'T', $date) . '.000Z';

        $x = ($this->roi->left() + $this->roi->right()) / 2;
        $y = ($this->roi->top() + $this->roi->bottom()) / 2;

        // The previous code swapped the two times before rotating.
        $auScalar = sunearth_distance($imageTime);
        [$rotatedX, $rotatedY] = rot_hpc($x, $y, $startTime, $imageTime, null, null, null);

        return [
            'au_scalar'                    => $auScalar,
            'hv_hpc_x_rot_delta_notscaled' => $rotatedX - $x,
            'hv_hpc_y_rot_delta_notscaled' => $rotatedY - $y,
        ];
    }

    private function inRange(string $start, string $end, string $date): bool
    {
        return strtotime($date) >= strtotime($start) && strtotime($date) <= strtotime($end);
    }

    // -------------------------------------------------------------------------
    // 4c. overlays
    // -------------------------------------------------------------------------

    /** A text style used by the overlays: DejaVu Sans, outlined or filled. */
    private function text(float $size, ?string $stroke, float $strokeWidth, ?float $strokeOpacity, ?string $fill, ?float $fillOpacity = null): ImagickDraw
    {
        $text = new ImagickDraw();
        $text->setTextEncoding('utf-8');
        $text->setFont(self::FONT);
        $text->setFontSize($size);
        if ($stroke !== null) {
            $text->setStrokeColor($stroke);
            $text->setStrokeAntialias(true);
            $text->setStrokeWidth($strokeWidth);
            if ($strokeOpacity !== null) {
                $text->setStrokeOpacity($strokeOpacity);
            }
        }
        if ($fill !== null) {
            $text->setFillColor($fill);
            if ($fillOpacity !== null) {
                $text->setFillOpacity($fillOpacity);
            }
            $text->setTextAntialias(true);
            $text->setStrokeWidth(0);
        }
        return $text;
    }

    /** A point in arcseconds to a pixel of the frame (y points up in arcseconds, down in pixels). */
    private function toPixel(float $x, float $y): array
    {
        return [
            (( $x - $this->roi->left()) / $this->roi->imageScale()) - $this->timeOffsetX,
            ((-$y - $this->roi->top())  / $this->roi->imageScale()) - $this->timeOffsetY,
        ];
    }

    /**
     * The events of this frame: first every event's region (far-side tints
     * and dashed ghosts, near-side fills) as one SVG rasterised by
     * rsvg-convert, then every event's marker pin and label on top. Frames
     * under 200 pixels get none.
     */
    private function drawEvents(Imagick $image, string $date): void
    {
        if ($this->roi->getPixelWidth() < 200 || $this->roi->getPixelHeight() < 200) {
            return;
        }
        $events = $this->events->getEventsForDate($date);
        if (empty($events)) {
            return;
        }

        // A shape's points to pixels; null when a coordinate is not finite
        // (ImageMagick would reject the whole drawing).
        $project = function (array $points): ?array {
            $out = [];
            foreach ($points as $p) {
                [$x, $y] = $this->toPixel($p['x'], $p['y']);
                if (!is_finite($x) || !is_finite($y)) {
                    return null;
                }
                $out[] = ['x' => $x, 'y' => $y];
            }
            return $out;
        };

        // The regions.
        $rendered = [];
        foreach ($events as $event) {
            if (empty($event['footprint'])) {
                continue;
            }
            $hex   = self::EVENT_COLORS[$event['type'] ?? ''] ?? 'd4d4d4';
            $tints = $ghosts = $fills = [];
            foreach ($event['footprint'] as $ring) {
                if (count($ring) < 2) {
                    continue;
                }
                $shapes = (new FootprintContour($ring))->shapes();
                $tints  = array_merge($tints,  $shapes['tints']);
                $ghosts = array_merge($ghosts, $shapes['ghosts']);
                $fills  = array_merge($fills,  $shapes['fills']);
            }
            $tintPolys = $ghostShapes = $fillPolys = [];
            foreach ($tints as $shape) {
                $p = $project($shape->points);
                if ($p !== null && count($p) >= 3) {
                    $tintPolys[] = $p;
                }
            }
            foreach ($ghosts as $shape) {
                $p = $project($shape->points);
                if ($p !== null && count($p) >= 2) {
                    $ghostShapes[] = ['pts' => $p, 'closed' => $shape->closed];
                }
            }
            foreach ($fills as $shape) {
                $p = $project($shape->points);
                if ($p !== null && count($p) >= 3) {
                    $fillPolys[] = $p;
                }
            }
            if (empty($tintPolys) && empty($ghostShapes) && empty($fillPolys)) {
                continue;
            }
            $rendered[] = ['hex' => $hex, 'tints' => $tintPolys, 'ghosts' => $ghostShapes, 'fills' => $fillPolys];
        }

        if (!empty($rendered)) {
            $canvasWidth  = (int) round($this->roi->getPixelWidth());
            $canvasHeight = (int) round($this->roi->getPixelHeight());
            $rasterizer   = new SvgRasterizer();
            if (!$rasterizer->isAvailable()) {
                throw new \RuntimeException('Event-region rendering requires rsvg-convert (librsvg), which was not found on PATH.');
            }
            $svg   = (new EventRegionSvg($rendered, $canvasWidth, $canvasHeight))->toSvg();
            $png   = $rasterizer->rasterize($svg, $canvasWidth, $canvasHeight);
            $layer = new Imagick();
            $layer->readImageBlob($png);
            $image->compositeImage($layer, Imagick::COMPOSITE_OVER, 0, 0);
            $layer->destroy();
        }

        // The pins and labels. A far-side event is dimmed and desaturated.
        $markerDir   = HV_ROOT_DIR . '/resources/images/eventMarkers';
        $markerCache = [];
        foreach ($events as $event) {
            $path = $markerDir . '/' . ($event['type'] ?? 'UNK') . '.png';
            if (!file_exists($path)) {
                $path = $markerDir . '/UNK.png';
            }
            if (!isset($markerCache[$path])) {
                $markerCache[$path] = new Imagick($path);
            }
            $marker = clone $markerCache[$path];

            $behind = (($event['visible'] ?? true) === false);
            if ($behind) {
                $marker->modulateImage(100, 70, 100);
                $marker->evaluateImage(Imagick::EVALUATE_MULTIPLY, 0.6, Imagick::CHANNEL_ALPHA);
            }

            $x = round(( $event['hv_hpc_x'] - $this->roi->left()) / $this->roi->imageScale()) - $this->timeOffsetX;
            $y = round((-$event['hv_hpc_y'] - $this->roi->top())  / $this->roi->imageScale()) - $this->timeOffsetY;

            // The pin's tip is 12 pixels from its left edge and 38 from its top.
            $image->compositeImage($marker, Imagick::COMPOSITE_DISSOLVE, $x - 12, $y - 38);
            $marker->destroy();

            if ($event['label'] !== '') {
                $x += 11;
                $y -= 24;
                foreach (explode("\n", $event['label']) as $line => $value) {
                    $value = str_replace(['u03b1', 'u03b2', 'u03b3', 'u00b1', 'u00b2', '&deg;'], ['α', 'β', 'γ', '±', '²', '°'], $value);
                    $image->annotateImage($this->text(10, '#000C', 3, $behind ? 0.18 : 0.3, null), $x, $y + $line * 12, 0, $value);
                    $image->annotateImage($this->text(10, null, 0, null, '#ffff', $behind ? 0.6 : null), $x, $y + $line * 12, 0, $value);
                }
            }
        }
        foreach ($markerCache as $m) {
            $m->destroy();
        }
    }

    /**
     * A pin and the layer names of every YouTube movie shared at this frame's
     * time, at the centre of its region.
     */
    private function drawMovieIcons(Imagick $image, string $date): void
    {
        $width  = $this->roi->getPixelWidth();
        $height = $this->roi->getPixelHeight();
        if ($width < 200 || $height < 200) {
            return;
        }

        $movies = new \Database_MovieDatabase();
        foreach ($movies->getSharedVideosByTime(0, 0, $date) as $video) {
            $layers = new \Helper_HelioviewerLayers($video['dataSourceString']);
            $name   = join(', ', array_column($layers->toArray(), 'name'));

            $videoRoi = \Helper_RegionOfInterest::parsePolygonString($video['roi'], $video['imageScale']);
            $top    = round($videoRoi->top()    / $videoRoi->imageScale());
            $left   = round($videoRoi->left()   / $videoRoi->imageScale());
            $bottom = round($videoRoi->bottom() / $videoRoi->imageScale());
            $right  = round($videoRoi->right()  / $videoRoi->imageScale());

            $xCenterOffset = ($this->roi->left() + $this->roi->right()) / 2 / $this->roi->imageScale();
            $yCenterOffset = ($this->roi->top() + $this->roi->bottom()) / 2 / $this->roi->imageScale();
            $x = -$xCenterOffset + $width  / 2 + (($right + $left) / 2) / $this->roi->imageScale() * (float) $video['imageScale'] - $this->timeOffsetX;
            $y = -$yCenterOffset + $height / 2 + (($top + $bottom) / 2) / $this->roi->imageScale() * (float) $video['imageScale'] - $this->timeOffsetY;

            $marker = new Imagick(HV_ROOT_DIR . '/resources/images/eventMarkers/movie.png');
            $image->compositeImage($marker, Imagick::COMPOSITE_DISSOLVE, $x - 10, $y);
            $marker->destroy();

            $image->annotateImage($this->text(10, '#000C', 3, 0.3, null), $x + 12, $y + 14, 0, $name);
            $image->annotateImage($this->text(10, null, 0, null, '#ffff'), $x + 12, $y + 14, 0, $name);
        }
    }

    /** "SOHO-Earth,SDO-Mars" → ['SOHO' => ['Earth'], 'SDO' => ['Mars']]. */
    private function parseCelestialBodiesSelection(string $selection): array
    {
        $parsed = [];
        foreach (explode(',', $selection) as $observerBody) {
            [$observer, $body] = explode('-', $observerBody);
            $parsed[$observer][] = $body;
        }
        return $parsed;
    }

    /**
     * A body's helioprojective position (arcseconds, as the module gives it)
     * to pixels from the centre of the frame, through heliocentric
     * cartesian coordinates at the body's distance.
     */
    private function celestialBodyToPixel($body): array
    {
        $distance           = $body->{'distance_sun_to_observer_au'} * 149597000000;
        $metersPerArcsecond = 724910;
        $hpcX = ($body->{'x'} / 3600) * (pi() / 180);
        $hpcY = ($body->{'y'} / 3600) * (pi() / 180);
        $hccX = $distance * tan($hpcX);
        $hccY = $distance * (tan($hpcY) / cos($hpcX));
        return [
            ( $hccX / $metersPerArcsecond) / $this->roi->imageScale(),
            -($hccY / $metersPerArcsecond) / $this->roi->imageScale(),
        ];
    }

    /** The names of the selected celestial bodies, at their positions at this frame's time. */
    private function drawCelestialBodiesLabels(Imagick $image, string $date): void
    {
        $unixTimeMs = (int) strtotime($date) * 1000;
        $module     = new \Module_SolarBodies(['time' => $unixTimeMs]);
        $glossary   = $module->getSolarBodiesGlossaryForScreenshot();
        $mods       = $glossary['mods'];
        $selected   = $this->parseCelestialBodiesSelection($this->celestialBodiesLabels);
        $labels     = $module->getSolarBodiesLabelsForScreenshot($unixTimeMs, $selected);

        $underText = $this->text(12, '#000', 4, 0.6, null);
        $text      = $this->text(12, null, 0, null, 'white');

        $width  = $this->roi->getPixelWidth();
        $height = $this->roi->getPixelHeight();
        $xCenterOffset = ($this->roi->left() + $this->roi->right()) / 2 / $this->roi->imageScale();
        $yCenterOffset = ($this->roi->top() + $this->roi->bottom()) / 2 / $this->roi->imageScale();

        $coordinates = $labels['labels'];
        foreach (array_reverse(array_keys($selected)) as $observer) {
            foreach (array_reverse($selected[$observer]) as $body) {
                if ($coordinates[$observer][$body] == null || !in_array($body, $selected[$observer])) {
                    continue;
                }
                [$px, $py] = $this->celestialBodyToPixel($coordinates[$observer][$body]);
                $x = -$xCenterOffset + $width  / 2 + $px - 2;
                $y = -$yCenterOffset + $height / 2 + $py + 12;

                $name = $body;
                if (isset($mods[$body])) {
                    $name = $mods[$body]['name'];
                    if ($mods[$body]['arrow']) {
                        $name = '↖' . $name;
                    }
                }
                if (0 < $x && $x < $width && 0 < $y && $y < $height) {
                    $image->annotateImage($underText, $x - 1, $y, 0, ucfirst($name));
                    $image->annotateImage($text, $x, $y, 0, ucfirst($name));
                }
            }
        }
        $underText->destroy();
        $text->destroy();
    }

    /**
     * The paths of the selected celestial bodies: a dot per step of the
     * body's cadence, joined by a line, solid in front of the Sun and dashed
     * behind it.
     */
    private function drawCelestialBodiesTrajectories(Imagick $image, string $date): void
    {
        $unixTimeMs   = (int) strtotime($date) * 1000;
        $module       = new \Module_SolarBodies(['time' => $unixTimeMs]);
        $mods         = $module->getSolarBodiesGlossaryForScreenshot()['mods'];
        $selected     = $this->parseCelestialBodiesSelection($this->celestialBodiesTrajectories);
        $trajectories = $module->getSolarBodiesTrajectoriesForScreenshot($unixTimeMs, $selected);

        $front  = new ImagickPixel('#A0A0A0');
        $behind = new ImagickPixel('#808080');
        $points = new ImagickDraw();
        $points->setFillColor($behind);
        // One drawing for the solid lines and one for the dashed: the imagick
        // extension cannot go back from dashed to solid (imagick issue 568).
        $solid = new ImagickDraw();
        $solid->setStrokeColor($behind);
        $solid->setFillColor(new ImagickPixel('transparent'));
        $dashed = new ImagickDraw();
        $dashed->setStrokeColor($behind);
        $dashed->setFillColor(new ImagickPixel('transparent'));
        $useDashed = false;
        $radius    = 2;

        $width  = $this->roi->getPixelWidth();
        $height = $this->roi->getPixelHeight();
        $xCenterOffset = ($this->roi->left() + $this->roi->right()) / 2 / $this->roi->imageScale();
        $yCenterOffset = ($this->roi->top() + $this->roi->bottom()) / 2 / $this->roi->imageScale();

        $setSide = function ($behindSun) use (&$useDashed, $points, $solid, $dashed, $front, $behind) {
            if ($behindSun == 'True') {
                $points->setFillColor($behind);
                $dashed->setStrokeColor($behind);
                $dashed->setStrokeDashArray([3, 2]);
                $useDashed = true;
            } elseif ($behindSun == 'False') {
                $points->setFillColor($front);
                $solid->setStrokeColor($front);
                $useDashed = false;
            }
        };

        $coordinates = $trajectories['trajectories'];
        foreach (array_reverse(array_keys($selected)) as $observer) {
            foreach (array_reverse($selected[$observer]) as $body) {
                if ($coordinates[$observer][$body] == null || !in_array($body, $selected[$observer])) {
                    continue;
                }
                $times    = array_keys((array) $coordinates[$observer][$body]);
                $line     = [];
                $lastTime = 0;
                $cadence  = isset($mods[$body]) ? (int) $mods[$body]['cadence'] : 86400000;
                $lastSide = $coordinates[$observer][$body]->{$times[0]}->{'behind_plane_of_sun'};
                $setSide($lastSide);

                foreach ($times as $time) {
                    if ($time - $lastTime < $cadence) {
                        continue;
                    }
                    $position = $coordinates[$observer][$body]->{$time};
                    [$px, $py] = $this->celestialBodyToPixel($position);
                    $x = -$xCenterOffset + $width  / 2 + $px - 2;
                    $y = -$yCenterOffset + $height / 2 + $py;

                    if ($position->{'behind_plane_of_sun'} != $lastSide) {
                        // The body crossed the plane of the Sun: finish the line
                        // on this side and start the other side's line here.
                        $line[] = ['x' => $x, 'y' => $y];
                        ($useDashed ? $dashed : $solid)->polyline($line);
                        $line = [['x' => $x, 'y' => $y]];
                        $setSide($position->{'behind_plane_of_sun'});
                    } else {
                        $line[] = ['x' => $x, 'y' => $y];
                    }
                    $points->circle($x, $y, $x, $y + $radius);
                    $lastTime = $time;
                    $lastSide = $position->{'behind_plane_of_sun'};
                }
                ($useDashed ? $dashed : $solid)->polyline($line);
            }
        }
        $image->drawImage($solid);
        $image->drawImage($dashed);
        $image->drawImage($points);

        $front->destroy();
        $behind->destroy();
        $points->destroy();
        $solid->destroy();
        $dashed->destroy();
    }

    /** The "Earth Scale" box: the Earth, to scale, in a framed box at the requested spot. */
    private function drawEarthScale(Imagick $image): void
    {
        $rsunInArcseconds   = 959.705;
        $earthFractionOfSun = 1 / 109.1;
        $earthScaleInPixels = round(2 * $earthFractionOfSun * ($rsunInArcseconds / $this->roi->imageScale()));

        [$left, $top] = $this->toPixelNoDrift($this->scaleX, $this->scaleY);
        $boxWidth  = 95;
        $boxHeight = max(82, $earthScaleInPixels + 22);

        $box = new ImagickDraw();
        $box->setFillColor('#00000066');
        $box->setStrokeColor('#888888FF');
        $box->rectangle($left, $top, $left + $boxWidth, $top + $boxHeight);
        $image->drawImage($box);

        if ($earthScaleInPixels >= 1) {
            $earth = new Imagick(HV_ROOT_DIR . '/resources/images/earth.png');
            $earth->resizeImage($earthScaleInPixels, $earthScaleInPixels, Imagick::FILTER_LANCZOS, 1);
            $image->compositeImage($earth, Imagick::COMPOSITE_DISSOLVE,
                intval(1 + $left + $boxWidth / 2 - $earthScaleInPixels / 2),
                intval(8 + $top + $boxHeight / 2 - $earthScaleInPixels / 2));
            $earth->destroy();
        }

        $title = new ImagickDraw();
        $title->setFillColor('#333333FF');
        $title->rectangle($left + 1, $top + 1, $left + $boxWidth - 1, $top + 16);
        $image->drawImage($title);
        $image->annotateImage($this->text(10, null, 0, null, '#ffff'), $left + 21, $top + 13, 0, 'Earth Scale');
    }

    /** The "Bar Scale" box: a 50-pixel bar and its length in km, at the requested spot or bottom left. */
    private function drawScaleBar(Imagick $image): void
    {
        $boxWidth  = 73;
        $boxHeight = 56;

        $earthInPixels = 2 * (6367.5 / 695500.0) * (959.705 / $this->roi->imageScale());
        $sizeInKm      = round((50 * (2 * 6367.5)) / $earthInPixels);
        $label         = number_format(round($sizeInKm / 1000) * 1000, 0, '.', ',') . ' km';

        if ($this->scaleX != 0 && $this->scaleY != 0) {
            [$left, $top] = $this->toPixelNoDrift($this->scaleX, $this->scaleY);
        } else {
            $left = -1;
            $top  = $image->getImageHeight() - $boxHeight;
        }

        $box = new ImagickDraw();
        $box->setFillColor('#00000066');
        $box->setStrokeColor('#888888FF');
        $box->rectangle($left, $top, $left + $boxWidth, $top + $boxHeight);
        $image->drawImage($box);

        $bar = new Imagick(HV_ROOT_DIR . '/resources/images/scalebar.png');
        $image->compositeImage($bar, Imagick::COMPOSITE_DISSOLVE, 1 + $left + $boxWidth / 2 - 25, 8 + $top + $boxHeight / 2 + 4);
        $bar->destroy();

        $title = new ImagickDraw();
        $title->setFillColor('#333333FF');
        $title->rectangle($left + 1, $top + 1, $left + $boxWidth - 1, $top + 16);
        $image->drawImage($title);
        $image->annotateImage($this->text(10, null, 0, null, '#ffff'), $left + 15, $top + 13, 0, 'Bar Scale');

        $km = $this->text(8, null, 0, null, '#ffff');
        $km->setTextAlignment(Imagick::ALIGN_CENTER);
        $image->annotateImage($km, $left + $boxWidth / 2 + 2, $top + 32, 0, $label);
    }

    /** A point in arcseconds to a pixel, ignoring the following viewport's drift (the scale boxes stay put). */
    private function toPixelNoDrift(float $x, float $y): array
    {
        return [
            ( $x - $this->roi->left()) / $this->roi->imageScale(),
            (-$y - $this->roi->top())  / $this->roi->imageScale(),
        ];
    }

    /**
     * The Helioviewer logo bottom right and, left of it, the name and time of
     * every layer. A frame under 200 pixels gets nothing, under 600 only the
     * round logo, under 286 no text.
     */
    private function drawWatermark(Imagick $image, array $layers): void
    {
        $width  = $this->roi->getPixelWidth();
        $height = $this->roi->getPixelHeight();
        if ($width < 200 || $height < 200) {
            return;
        }

        $logo = new Imagick(HV_ROOT_DIR . '/resources/images/watermark_small_black_border.png');
        if ($width < 600) {
            $logo->readImage(HV_ROOT_DIR . '/resources/images/watermark_circle_small_black_border.png');
            $side = intval($logo->getImageWidth() * $width / 600);
            $logo->scaleImage($side, $side);
        }
        $image->compositeImage($logo, Imagick::COMPOSITE_DISSOLVE,
            intval($width - $logo->getImageWidth() - 10), intval($height - $logo->getImageHeight() - 10));
        $logo->destroy();

        if ($width > 285) {
            $this->drawWatermarkText($image, $layers);
        }
    }

    /**
     * The layer names in one column and their times in a second column
     * right of the widest name, bottom left, outlined in black.
     */
    private function drawWatermarkText(Imagick $image, array $layers): void
    {
        $names = '';
        $times = '';
        $bottom = $image->getImageHeight() - 15;
        foreach ($layers as $layer) {
            $bottom -= 10;
            $names  .= $layer['name'];
            $times  .= $layer['date'];
        }
        // Room for the Earth scale box when it sits in the corner.
        $left = ($this->scale == false || $this->scaleX != 0 || $this->scaleY != 0) ? 12 : 85;

        $underText = $this->text(12, '#000C', 2, null, null);
        $image->annotateImage($underText, $left, $bottom, 0, $names);
        $metrics = $image->queryFontMetrics($underText, $names, true);
        $image->annotateImage($underText, $left + ($metrics['textWidth'] ? $metrics['textWidth'] + 10 : 120), $bottom, 0, $times);

        $text = $this->text(12, null, 0, null, 'white');
        $image->annotateImage($text, $left, $bottom, 0, $names);
        $metrics = $image->queryFontMetrics($text, $names, true);
        $image->annotateImage($text, $left + ($metrics['textWidth'] ? $metrics['textWidth'] + 10 : 120), $bottom, 0, $times);

        $underText->destroy();
        $text->destroy();
    }

    // =========================================================================
    // 5. previews
    // =========================================================================

    /** preview-full.png and four thumbnails, letterboxed in black, from the middle frame. */
    private function writePreviews(Imagick $frame): void
    {
        $frame->setImageCompression(Imagick::COMPRESSION_LZW);
        $frame->setImageCompressionQuality(PNG_LOW_COMPRESSION);
        $frame->setInterlaceScheme(Imagick::INTERLACE_PLANE);

        $sizes = ['large' => [640, 480], 'medium' => [320, 240], 'small' => [240, 180], 'icon' => [64, 64]];
        foreach ($sizes as $name => [$width, $height]) {
            $thumb = clone $frame;
            $thumb->thumbnailImage($width, $height, true);
            $thumb->borderImage('black', ceil(($width - $thumb->getImageWidth()) / 2), ceil(($height - $thumb->getImageHeight()) / 2));
            $thumb->cropImage($width, $height, 0, 0);
            $thumb->writeImage($this->directory . 'preview-' . $name . '.png');
            $thumb->destroy();
        }
        $frame->writeImage($this->directory . 'preview-full.png');
    }

    // =========================================================================
    // 6. encode
    // =========================================================================

    /**
     * The video files, with ffmpeg: the hq mp4 from the frames, the mp4 from
     * the hq one, the flv as a copy of the mp4's stream, and the webm from
     * the frames. The mp4 row is marked finished after the flv, the webm row
     * after the webm.
     */
    private function encodeMovie(): void
    {
        $start = microtime(true);

        $layerString = $this->layers->toHumanReadableString();
        $dateString  = $this->dateString();
        $playUrl     = HV_WEB_ROOT_URL . '/?action=playMovie&id=' . $this->publicId . '&format=mp4&hq=true';
        $downloadUrl = HV_WEB_ROOT_URL . '/?action=downloadMovie&id=' . $this->publicId . '&format=mp4&hq=true';
        $title       = sprintf('%s (%s)', $layerString, $dateString);
        $description = sprintf('The Sun as seen through %s from %s.', $layerString, str_replace('-', ' to ', $dateString));
        $comment     = sprintf('This movie was produced by Helioviewer.org. See the original at %s or download a high-quality version from %s.', $playUrl, $downloadUrl);
        $metadata    = function (string $format) use ($title, $description, $comment): string {
            return sprintf('-metadata title="%s" -metadata artist="Helioviewer.org" -metadata description="%s" -metadata comment="%s" -metadata creation_time="%s" ',
                $title, $description, str_replace('mp4', $format, $comment), date("Y-m-d\TH:i:s\Z"));
        };

        // The frame rate with one decimal (launchpad bug 979231), twice in
        // every command, before and after the input, or ffmpeg drops frames.
        $frameRate = round($this->frameRate, 1);
        [$width, $height] = self::SIZE_PRESETS[$this->size] ?? [$this->width, $this->height];
        $frames = $this->directory . 'frames/frame%d.bmp';
        $base   = $this->directory . substr($this->filename, 0, -4);
        $this->ffmpegLog = fopen($this->directory . 'ffmpeg.log', 'a');

        $mp4Start = time();
        $this->ffmpeg(sprintf('%s -r %s -i %s -r %s -vcodec libx264 -pix_fmt yuv420p -preset %s %s-threads %d -crf 15 -s %dx%d -an -y %s-hq.mp4',
            HV_FFMPEG, $frameRate, $frames, $frameRate, HV_X264_HQ_PRESET, $metadata('mp4'), HV_FFMPEG_MAX_THREADS, $width, $height, $base), $base . '-hq.mp4');
        $this->ffmpeg(sprintf('%s -i %s-hq.mp4 -preset %s  -threads %d -crf 18 %s.mp4',
            HV_FFMPEG, $base, HV_X264_PRESET, HV_FFMPEG_MAX_THREADS, $base), $base . '.mp4');
        $this->ffmpeg(sprintf('%s -i %s.mp4 -vcodec copy %s -threads %d %s.flv',
            HV_FFMPEG, $base, $metadata('mp4'), HV_FFMPEG_MAX_THREADS, $base), $base . '.flv');
        $this->db->markMovieAsFinished($this->id, 'mp4', time() - $mp4Start);

        $webmStart = time();
        $this->ffmpeg(sprintf('%s -r %f -i %s -r %f -f webm -vcodec libvpx -pix_fmt yuv420p -qmin 1 -qmax 40 %s -threads %d -s %dx%d -an -y %s.webm',
            HV_FFMPEG, $frameRate, $frames, $frameRate, $metadata('webm'), HV_FFMPEG_MAX_THREADS, $width, $height, $base), $base . '.webm');
        $this->db->markMovieAsFinished($this->id, 'webm', time() - $webmStart);

        fclose($this->ffmpegLog);
        $this->status = self::STATUS_COMPLETED;
        $this->timings['encode'] = microtime(true) - $start;
    }

    /** Runs one ffmpeg command, logged to ffmpeg.log, and checks that it produced a file. */
    private function ffmpeg(string $cmd, string $outputFile): void
    {
        fwrite($this->ffmpegLog, "========================================================\n$cmd\n");
        shell_exec($cmd . ' 2>/dev/null');

        // A crashed ffmpeg may still leave an empty container behind.
        if (!file_exists($outputFile)) {
            throw new Exception("FFMpeg error encountered - movie file $outputFile does not exist");
        }
        if (filesize($outputFile) < 1000) {
            throw new Exception("FFmpeg error encountered - Expected movie to be at least 1000 bytes, got " . filesize($outputFile) . " ($outputFile)", 43);
        }
    }

    /** "2024-01-01 00:00:00 - 12:00:00 UTC", the end date dropped when it is the start's day. */
    private function dateString(): string
    {
        if (substr($this->startDate, 0, 9) == substr($this->endDate, 0, 9)) {
            $end = substr($this->endDate, 11);
        } else {
            $end = $this->endDate;
        }
        return sprintf('%s - %s UTC', $this->startDate, $end);
    }

    // =========================================================================
    // 7. finish
    // =========================================================================

    /** Deletes the frames and their folder (kept for the tests when asked). */
    private function deleteFrames(): void
    {
        if ($this->keepFrames) {
            return;
        }
        $dir = $this->directory . 'frames/';
        if (@file_exists($dir)) {
            foreach (glob($dir . '*') as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
}
