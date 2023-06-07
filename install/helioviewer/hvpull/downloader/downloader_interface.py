from threading import Thread
from queue import Queue

class Downloader(Thread):
    def __init__(self, incoming_dir: str, queue: Queue):
        """
        Creates a Downloader thread
        - incoming_dir - Path to data files
        - queue - Shared queue object that this process will read from
        """
        super().__init__()
        self.shutdown_requested = False
        self.incoming = incoming_dir
        self.queue = queue
        self._failure_flag = False

    def has_failed(self) -> bool:
        """
        returns whether or not this downloader has failed
        """
        return self._failure_flag

    def flag_failure(self):
        """
        Flags that this downloader has failed.
        Subsequent calls to has_failed will return True
        """
        self._failure_flag = True

    def stop(self):
        self.shutdown_requested = True

    def run(self):
        while not self.shutdown_requested:
            # If the downloader has failed, then just mark that the operation is done
            # without any further processing. The main downloader daemon will handle the failure
            # task_done must be called so callers don't hang if queue.join was called.
            item = self.queue.get()
            if (not self.has_failed()):
                self.process(item)
            self.queue.task_done()

    def process(self):
        """
        To be implemented by derived classes.
        This process is run in a loop while the thread is running.
        When this function ends/returns it is called again immediately.

        If flag_failure has been called, then this function will stop being executed.
        """
        raise NotImplementedError("Attempted to run downloader on interface, not a real instance")

