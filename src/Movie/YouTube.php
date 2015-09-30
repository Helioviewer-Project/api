<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Movie_YouTube Class Definition
 * Uploads user-created movies to YouTube
 *
 * @category Movie
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

class Movie_YouTube {

    private $_appId;
    private $_clientId;
    private $_testURL;
    private $_uploadURL;
    private $_httpClient;
    private $_youTube;

    /**
     * Creates a new YouTube instance
     *
     * @return void
     */
    public function __construct() {

        include_once 'GoogleSDK/src/Google/autoload.php';

        $this->_appId     = 'Helioviewer.org User Video Uploader';
        $this->_clientId  = 'Helioviewer.org (2.4.0)';

        //$this->_testURL   = 'http://gdata.youtube.com/feeds/api/users/default/uploads?max-results=1';
        //$this->_uploadURL = 'http://uploads.gdata.youtube.com/feeds/api/users/default/uploads';

        if ( !isset($_SESSION) ) {
            # Note: If same user begins upload, and then shortly after tried
            # to upload a video again, PHP may hang when attempting to call
            # session_start(). May need a better way to make sure nothing else
            # is going on? (e.g. check for video entry in YouTube)
            session_start();
        }

		
		$this->_httpClient = new Google_Client();
		$this->_httpClient->setApplicationName("Helioviewer");
		$this->_httpClient->setClientId(HV_GOOGLE_OAUTH2_CLIENT_ID);
		$this->_httpClient->setClientSecret(HV_GOOGLE_OAUTH2_CLIENT_SECRET);
		$this->_httpClient->setScopes('https://www.googleapis.com/auth/youtube.upload');
		
        // Post-auth upload URL
        $redirect = filter_var(HV_WEB_ROOT_URL . '?action=uploadMovieToYouTube&html=true', FILTER_SANITIZE_URL);
		$this->_httpClient->setRedirectUri($redirect);
		
    }

    /**
     * Authenticates user and uploads video to YouTube
     *
     * @param Helioviewer_Movie A Movie object
     *
     * @return void
     */
    public function uploadVideo($movie, $id, $title, $description, $tags, $share, $html) {

        // Re-confirm authorization and convert single-use token to session
        // token if applicable
        $this->_checkAuthorization();
        
        // Once we have a session token get an AuthSubHttpClient
        $this->_httpClient->setAccessToken($_SESSION['sessionToken']);

        // Increase timeout time to prevent client from timing out
        // during uploads
        //$this->_httpClient->setConfig(array('timeout' => 600));

        // Creates an instance of the Youtube GData object
        $this->_youTube = $this->_getYoutubeInstance();

        // If authorization is expired, reauthorize
        if ( !$this->_authorizationIsValid() ) {
            //$this->getYouTubeAuth($movie->id);
            $this->getYouTubeAuth($id);
        }

        $filepath = $movie->getFilepath(true);

        $this->uploadVideoToYouTube($filepath, $id, $title, $description, $tags, $share, $html);
    }

    /**
     * Checks to see if a user is Helioveiwer.org is currently authorized to
     * interact with a user's YouTube account.
     *
     * @return bool Returns true if the user has been authenticated by YouTube
     */
    public function checkYouTubeAuth() {

        if ( !isset($_SESSION['sessionToken']) ) {
            return false;
        }
		
		$this->_httpClient->setAccessToken($_SESSION['sessionToken']);
        $this->_youTube    = $this->_getYoutubeInstance();

        return $this->_authorizationIsValid();
    }

    /**
     * Requests authorization for Helioviewer.org to upload videos on behalf
     * of the user.
     */
    public function getYouTubeAuth($id) {

        // Get URL for authorization
        $authURL = $this->_httpClient->createAuthUrl();

        // Redirect user to YouTube authorization page
        header('Location: '.$authURL);
    }

    /**
     * Authorizes Helioviewer to upload videos to the user's account.
     *
     * Function first checks to see if a session token already exists. If none
     * is found, the user is either redirected to an authentication URL, or
     * if stores sessions token if it was just retrieved.
     *
     * @param string $url Upload query URL
     *
     * @return void
     */
    private function _checkAuthorization() {

        if ( !isset($_SESSION['sessionToken']) ) {
            // If no session token exists, check for single-use URL token
            if ( isset($_GET['token']) ) {
	            $this->_httpClient->authenticate($_GET['token']);
	            $_SESSION['sessionToken'] = $this->_httpClient->getAccessToken();
            }else if ( isset($_GET['code']) ) {
	            $this->_httpClient->authenticate($_GET['code']);
	            $_SESSION['sessionToken'] = $this->_httpClient->getAccessToken();
            }else {
                // Otherwise, send user to authorization page
                throw new Exception('Authorization required before movie can be uploaded.', 45);
            }
        }
    }

    /**
     * Checks to see if the user is currently authenticated, and that any
     * previous authentication is not expired
     *
     * @return bool Returns true if the user is authenticated and that
     * authentication is still good, and false otherwise
     */
    private function _authorizationIsValid() {
        if ( $this->_httpClient->isAccessTokenExpired() ) {
	        unset($_SESSION['sessionToken']);
	        return false;
        }if ($this->_httpClient->getAccessToken()) {
	        return true;
        }else{
	        //Zend_Gdata_App_HttpException
            include_once HV_ROOT_DIR.'/../src/Helper/ErrorHandler.php';
            logErrorMsg($msg, 'Youtube_');

            // Discard expired authorization
            unset($_SESSION['sessionToken']);

            return false;
        }
        return true;
    }


    /**
     * Initializes a YouTube GData object instance
     *
     * @return Zend_Gdata_YouTube A YouTube object instance
     */
    private function _getYoutubeInstance() {

        // Instantiate Youtube object
		$yt = new Google_Service_YouTube($this->_httpClient);

        return $yt;
    }
	
	/**
     * Uploads a single video to YouTube
     *
     * @param string                           	$filepath       Movie id
     * @param int                           	$id         	Movie id
     * @param string                           	$title         	Movie id
     * @param string                           	$description    Movie id
     * @param array                           	$tags         	Movie id
     * @param boolean                       	$share    		Movie options
     * @param boolean                       	$html    		Movie options
     *
     */
     public function uploadVideoToYouTube($filepath, $id, $title, $description, $tags, $share, $html) {
		// Create a snippet with title, description, tags and category ID
		// Create an asset resource and set its snippet metadata and type.
		// This example sets the video's title, description, keyword tags, and
		// video category.
		$snippet = new Google_Service_YouTube_VideoSnippet();
		$snippet->setTitle($title);
		$snippet->setDescription($description);
		$snippet->setTags($tags);
		//$snippet->setPublishedAt(array('Helioviewer.org'));
		
		// Numeric video category. See
		// https://developers.google.com/youtube/v3/docs/videoCategories/list 
		$snippet->setCategoryId("28");
		
		// Set the video's status to "public". Valid statuses are "public",
		// "private" and "unlisted".
		$status = new Google_Service_YouTube_VideoStatus();
		$status->privacyStatus = "public";
		
		// Associate the snippet and status objects with a new video resource.
		$video = new Google_Service_YouTube_Video();
		$video->setSnippet($snippet);
		$video->setStatus($status);
		
		//Proceed to upload
		$this->_uploadVideoToYouTube($video, $filepath, $id, $title, $description, $tags, $share, $html);

	}

    /**
     * Uploads a single video to YouTube
     *
     * @param int                           $id         Movie id
     * @param array                         $options    Movie options
     * @param Zend_Gdata_YouTube_VideoEntry $videoEntry A video entry object
     * describing the video to be uploaded
     *
     * Note: Content-length and Connection: close headers are sent so that
     * process can be run in the background without the user having to wait,
     * and the database entry will be updated even if the user closes the
     * browser window.
     *
     * See:
     * http://zulius.com/how-to/close-browser-connection-continue-execution/
     *
     * @return Zend_Gdata_YouTube_VideoEntry
     */
    private function _uploadVideoToYouTube($video, $filepath, $id, $title, $description, $tags, $share, $html) {

        include_once HV_ROOT_DIR.'/../src/Database/MovieDatabase.php';
        include_once HV_ROOT_DIR.'/../lib/alphaID/alphaID.php';

        $movies = new Database_MovieDatabase();

        // Add movie entry to YouTube table if entry does not already exist
        $movieId = alphaID($id, true, 5, HV_MOVIE_ID_PASS);

        if ( !$movies->insertYouTubeMovie($movieId, $title, $description, $tags, $share) ) {

            throw new Exception('Movie has already been uploaded. Please allow several minutes for your video to appear on YouTube.', 46);
        }

        // buffer all upcoming output
        ob_start();

        // let user know that upload is in progress
        if ($html) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Helioviewer.org - YouTube Upload In Progress</title>
    <link rel="shortcut icon" href="../favicon.ico">
    <meta charset="utf-8" />
</head>
<body style='text-align: center;'>
    <div style='margin-top: 200px;'>
        <span style='font-size: 32px;'>Upload processing</span><br />
        Your video should appear on YouTube in 1-2 minutes.
    </div>
</body>
<?php
        }
        else {
            header('Content-type: application/json');
            echo json_encode(array('status' => 'upload in progress.'));
        }

        // get the size of the output
        $size = ob_get_length();

        // send headers to tell the browser to close the connection
        header('Content-Length: '.$size);
        header('Connection: close');

        // flush all output
        ob_end_flush();
        ob_flush();
        flush();

        // close current session
        if ( session_id() ) {
            session_write_close();
        }

        // Begin upload
        try {
            // Specify the size of each chunk of data, in bytes. Set a higher value for
			// reliable connection as fewer chunks lead to faster uploads. Set a lower
			// value for better recovery on less reliable connections.
			$chunkSizeBytes = 1 * 1024 * 1024;
            
            // Setting the defer flag to true tells the client to return a request which can be called
			// with ->execute(); instead of making the API call immediately.
			$this->_httpClient->setDefer(true);
			
			// Create a request for the API's videos.insert method to create and upload the video.
			$insertRequest = $this->_youTube->videos->insert("status,snippet", $video);
			
			// Create a MediaFileUpload object for resumable uploads.
			$media = new Google_Http_MediaFileUpload(
			    $this->_httpClient,
			    $insertRequest,
			    'video/*',
			    null,
			    true,
			    $chunkSizeBytes
			);
			$media->setFileSize(filesize($filepath));

			// Read the media file and upload it chunk by chunk.
			$status = false;
			$handle = fopen($filepath, "rb");
			while (!$status && !feof($handle)) {
				$chunk = fread($handle, $chunkSizeBytes);
				$status = $media->nextChunk($chunk);
			}
			
			fclose($handle);
			
			// If you want to make other calls after the file upload, set setDefer back to false
			$this->_httpClient->setDefer(false);
        }
        catch (Zend_Gdata_App_HttpException $httpException) {
            throw($httpException);
        }
        catch (Zend_Gdata_App_Exception $e) {
            throw($e);
        }

        // Update database entry and return result
        $movies->updateYouTubeMovie($movieId, $status['id']);

        return $media;
    }
}
?>
