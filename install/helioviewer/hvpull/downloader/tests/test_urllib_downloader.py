from ..urllib import URLLibDownloader
from queue import Queue

def test_urllib_downloader():
    """
    Makes sure the downloader stops after 10 failures have been reached
    """
    queue = Queue()
    downloader = URLLibDownloader("/tmp", queue)
    downloader.setDaemon(True)
    downloader.start()

    # Put 10 pieces of junk in the queue to cause a failure
    for i in range(10):
        queue.put(["testing", 10, "https://helioviewer.org/jp2/boop"])
    queue.join()

    assert downloader.has_failed()

    # Make sure requesting more garbage doesn't increase the failure count.
    # This confirms it's not processing any new requests
    queue.put(["testing", 10, "https://helioviewer.org/jp2/boop"])
    queue.join()
    assert downloader.failure_count == 10
