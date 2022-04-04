getYouTubeAuth
^^^^^^^^^^^^^^
GET /v2/getYouTubeAuth/

Request authorization from the user via a Google / YouTube authorization flow to
permit Helioviewer to upload videos on behalf of the user.

.. table:: Request Parameters:

    +-------------+----------+---------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+-------------------------------------------------------------------------------+
    |  Parameter  | Required |  Type   |                                                                                                        Example                                                                                                         |                                  Description                                  |
    +=============+==========+=========+========================================================================================================================================================================================================================+===============================================================================+
    | id          | Required | string  | VXvX5                                                                                                                                                                                                                  | Unique movie identifier (provided by the response to a `queueMovie` request). |
    +-------------+----------+---------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+-------------------------------------------------------------------------------+
    | title       | Required | string  | AIA 4500 (2013-12-30 16:00:07 - 2014-01-27 15:00:07 UTC)                                                                                                                                                               | Movie title.                                                                  |
    +-------------+----------+---------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+-------------------------------------------------------------------------------+
    | description | Required | string  | This movie was produced by Helioviewer.org. See the original at http://helioviewer.org/?movieId=z6vX5 or download a high-quality version from http://api.helioviewer.org/v2/downloadMovie/?id=z6vX5&format=mp4&hq=true | Move description.                                                             |
    +-------------+----------+---------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+-------------------------------------------------------------------------------+
    | tags        | Required | string  | SDO,AIA,4500                                                                                                                                                                                                           | Movie keyword tags (comma separated).                                         |
    +-------------+----------+---------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+-------------------------------------------------------------------------------+
    | share       | Optional | boolean | true                                                                                                                                                                                                                   | Optionally share the movie with the Helioviewer community.                    |
    +-------------+----------+---------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+-------------------------------------------------------------------------------+

Example: string (HTTP Header redirect)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Redirect user to YouTube authorization page.

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getYouTubeAuth/?id=VXvX5&title=AIA%204500%20(2013-12-30%2016%3A00%3A07%20-%202014-01-27%2015%3A00%3A07%20UTC)&description=This%20movie%20was%20produced%20by%20Helioviewer.org.%20See%20the%20original%20at%20http%3A%2F%2Fhelioviewer.org%2F%3FmovieId%3Dz6vX5%20or%20download%20a%20high-quality%20version%20from%20http%3A%2F%2Fhelioviewer.org%2Fapi%2F%3Faction%3DdownloadMovie%26id%3Dz6vX5%26format%3Dmp4%26hq%3Dtrue&tags=SDO%2CAIA%2C4500&share=true

.. code-block::
    :caption: Example Response:

    Location: https://www.google.com/accounts/AuthSubRequest?next=http%3A%2F%2Fhelioviewer.org%2Fapi%2Findex.php%3Faction%3DuploadMovieToYouTube%26id%3DVXvX5%26html%3Dtrue&scope=http://gdata.youtube.com&secure=&session=1
