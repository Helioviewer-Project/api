getNewsFeed
^^^^^^^^^^^
GET /v2/getNewsFeed/

Get the XML RSS feed of the official Helioviewer Project Blog.

.. table:: Request Parameters:

    +-----------+----------+--------+---------+---------------------------------------------------------------+
    | Parameter | Required |  Type  | Example |                          Description                          |
    +===========+==========+========+=========+===============================================================+
    | callback  | Optional | string |         | Wrap the response object in a function call of your choosing. |
    +-----------+----------+--------+---------+---------------------------------------------------------------+

Example: string (RSS XML)
~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getNewsFeed/?

.. code-block::
    :caption: Example Response:

    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0"
        xmlns:content="http://purl.org/rss/1.0/modules/content/"
        xmlns:wfw="http://wellformedweb.org/CommentAPI/"
        xmlns:dc="http://purl.org/dc/elements/1.1/"
        xmlns:atom="http://www.w3.org/2005/Atom"
        xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
        xmlns:slash="http://purl.org/rss/1.0/modules/slash/" >

    <channel>
        <title>The Helioviewer Project Blog</title>
        <atom:link href="http://blog.helioviewer.org/feed/" rel="self" type="application/rss+xml" />
        <link>http://blog.helioviewer.org</link>
        <description>Explore your heliosphere</description>
        <lastBuildDate>Tue, 28 Jan 2014 15:16:51 +0000</lastBuildDate>
        <language>en</language>
            <sy:updatePeriod>hourly</sy:updatePeriod>
            <sy:updateFrequency>1</sy:updateFrequency>
        <generator>http://wordpress.org/?v=3.8.1</generator>
        <item>
            <title>Follow the Helioviewer Project on Twitter</title>
            <link>http://blog.helioviewer.org/2014/01/28/follow-the-helioviewer-project-on-twitter/</link>
            <comments>http://blog.helioviewer.org/2014/01/28/follow-the-helioviewer-project-on-twitter/#comments</comments>
            <pubDate>Tue, 28 Jan 2014 15:16:51 +0000</pubDate>
            <dc:creator><![CDATA[jack]]></dc:creator>
                    <category><![CDATA[General]]></category>

            <guid isPermaLink="false">http://blog.helioviewer.org/?p=1265</guid>
            <description><![CDATA[The Helioviewer Project is now on Twitter, @Helioviewer. Please follow us on Twitter for the latest solar and heliospheric news and movies, as well as new Helioviewer Project features.]]></description>
                    <content:encoded><![CDATA[<p>The Helioviewer Project is now on Twitter, @Helioviewer.</p>
    <p><a href="http://blog.helioviewer.org/wp-content/uploads/2014/01/twitter_hv.png"><img src="http://blog.helioviewer.org/wp-content/uploads/2014/01/twitter_hv.png" alt="twitter_hv" width="538" height="268" class="aligncenter size-full wp-image-1266" /></a></p>
    <p>Please <a href="https://twitter.com/Helioviewer" title="Helioviewer on Twitter">follow us on Twitter</a> for the latest solar and heliospheric news and movies, as well as new Helioviewer Project features.</p>
    ]]></content:encoded>
                <wfw:commentRss>http://blog.helioviewer.org/2014/01/28/follow-the-helioviewer-project-on-twitter/feed/</wfw:commentRss>
            <slash:comments>0</slash:comments>
        </item>
    </channel>
    </rss>
