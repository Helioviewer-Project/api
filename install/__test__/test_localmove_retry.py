"""Tests for LocalFileMove retry limit functionality"""
import unittest
from unittest.mock import patch, MagicMock
from queue import Queue

from helioviewer.hvpull.downloader.localmove import LocalFileMove, MAX_RETRY_ATTEMPTS


class TestLocalFileMoveRetry(unittest.TestCase):
    """Test that file move retries are limited to MAX_RETRY_ATTEMPTS"""

    def setUp(self):
        self.queue = Queue()
        self.mover = LocalFileMove("/tmp/incoming", self.queue)

    @patch('shutil.move')
    @patch('os.path.exists', return_value=True)
    def test_successful_move_does_not_retry(self, mock_exists, mock_move):
        """A successful move should not add anything to the queue"""
        self.mover.process(["server1", 100, "/source/file.jp2"])

        mock_move.assert_called_once()
        self.assertTrue(self.queue.empty())

    @patch('shutil.move', side_effect=IOError("File busy"))
    @patch('os.path.exists', return_value=True)
    def test_failed_move_retries_up_to_max(self, mock_exists, mock_move):
        """A failing move should retry MAX_RETRY_ATTEMPTS times then give up"""
        # First attempt (retry_count=0)
        self.mover.process(["server1", 100, "/source/file.jp2"])

        # Should be re-queued with retry_count=1
        self.assertEqual(self.queue.qsize(), 1)
        item = self.queue.get()
        self.assertEqual(item[3], 1)  # retry_count = 1

        # Second attempt (retry_count=1)
        self.mover.process(item)
        item = self.queue.get()
        self.assertEqual(item[3], 2)  # retry_count = 2

        # Third attempt (retry_count=2)
        self.mover.process(item)
        item = self.queue.get()
        self.assertEqual(item[3], 3)  # retry_count = 3

        # Fourth attempt (retry_count=3) - should give up, not re-queue
        self.mover.process(item)
        self.assertTrue(self.queue.empty(), "Should not retry after MAX_RETRY_ATTEMPTS")

    def test_max_retry_attempts_is_three(self):
        """Verify the constant is set to 3"""
        self.assertEqual(MAX_RETRY_ATTEMPTS, 3)


if __name__ == '__main__':
    unittest.main()
