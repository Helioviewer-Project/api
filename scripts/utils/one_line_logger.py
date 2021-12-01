class OneLineLogger:
    """
    Logs data from a set position in a file.
    Good for writing status to a file without blowing up the file size.
    Be careful when using this with other logging systems as this may mess
    up the log file by changing the seek position.
    """
    def __init__(self, log_file):
        self._seek_position = 0
        self._position_locked = False
        self.fp = open(log_file, 'w')        
    
    def __del__(self):
        self.fp.close()
    
    def lock_position(self):
        """
        Locks the file pointer position all logs will write starting at this
        byte offset.
        """
        self._position_locked = True
        self._seek_position = self.fp.tell()
    
    def unlock_position(self):
        """
        Allows log calls to append to the file instead of truncating it back
        to the locked position
        """
        self._position_locked = False
    
    def _rewind_fp(self):
        self.fp.truncate(self._seek_position)
        self.fp.seek(self._seek_position)
    
    def log(self, message):
        if self._position_locked:
            self._rewind_fp()
        
        self.fp.write(message)
        self.fp.write('\n')
        # Flush to file so data isn't just buffered in memory. Other users or
        # programs must be able to read the status from the log file.
        self.fp.flush()