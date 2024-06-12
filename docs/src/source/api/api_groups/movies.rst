Movies
------

The movie APIs can be used to generate custom videos of up to three image
datasource layers composited together. Solar feature/event markers pins,
extended region polygons, associated text labels, and a size-of-earth scale
indicator can optionally be overlayed onto a movie.

Movie generation is performed asynchronously due to the amount of resources
required to complete each video. Movie requests are queued and then processed
(in the order in which they are received) by one of several worker processes
operating in parallel.

As a user of the API, begin by sending a 'queueMovie' request. If your request
is successfully added to the queue, you will receive a response containing a
unique movie identifier. This identifier can be used to monitor the status of
your movie via 'getMovieStatus' and then download or play it (via 'downloadMovie'
or 'playMovie') once its status marked as completed.

Movies may contain between 10 and 300 frames. The movie frames are chosen by
matching the closest image available at each step within the specified range of
dates, and are automatically selected by the API. The region to be included in
the movie may be specified using either the top-left and bottom-right coordinates
in arc-seconds, or a center point in arc-seconds and a width and height in pixels.
See the Coordinates Appendix for more infomration about working with the coordinates
used by Helioviewer.org.

.. include:: movies/queueMovie.rst
.. include:: movies/postMovie.rst
.. include:: movies/reQueueMovie.rst
.. include:: movies/getMovieStatus.rst
.. include:: movies/downloadMovie.rst
.. include:: movies/playMovie.rst
