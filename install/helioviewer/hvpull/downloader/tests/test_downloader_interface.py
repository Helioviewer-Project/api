from ..downloader_interface import Downloader
from queue import Queue

class FakeDownloader(Downloader):
    def __init__(self, incoming, queue):
        super().__init__(incoming, queue)
        self.items_processed = 0
        self.failure_limit = 10

    def set_failure_limit(self, limit: int):
        """
        Once this instance processes this many items, it will set the failure flag
        """
        self.failure_limit = limit

    def process(self, item):
        self.items_processed += 1
        if (self.items_processed > self.failure_limit):
            self.flag_failure()

def test_DownloaderStopsProcessingUponFailure():
    """
    This test verifies that the Downloader parent class behaves as expected.

    """
    test_queue = Queue()
    fake_downloader = FakeDownloader("/test", test_queue)
    fake_downloader.setDaemon(True)
    fake_downloader.start()
    fake_downloader.set_failure_limit(10)

    # First 10 requests will be fine.
    for i in range(10):
        test_queue.put(i)
    test_queue.join()

    # Assert that the queue processed those 10 items, and that it's healthy
    assert fake_downloader.items_processed == 10
    assert not fake_downloader.has_failed()

    # Now exceed the failure limit
    test_queue.put(1)
    test_queue.join()

    # 11 items should be processed, and failure flag is set
    assert fake_downloader.items_processed == 11
    assert fake_downloader.has_failed()

    # New items added to the queue should not be processed, but the queue should still join
    test_queue.put(999)
    test_queue.join()
    assert fake_downloader.items_processed == 11
    assert fake_downloader.has_failed()

