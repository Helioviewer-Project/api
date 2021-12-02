class DatabasePager:
    """
    Helper class to query the database in chunks to limit memory usage
    """

    def __init__(self, cursor, retrieve_sql, count_sql=''):
        """
        Construct the database pager

        Args:
        - cursor : Database connection cursor object
        - retrieve_sql : SQL that would return ALL rows when executed.
            * Note: The pager will append "LIMIT #, #" to your retrieve_sql query.
        - count_sql : SQL to be executed that will return the total number of rows to be retrieved
            * Note count_sql is only needed if you wish to execute get_image_count

        """
        self._cursor = cursor
        self._count_sql = count_sql
        self._retrieve_sql = retrieve_sql + " LIMIT %s, %s"
        # Modify this take value to optimize for memory usage.
        # Default is set to query 5 million rows.
        # Assume each row is approximately 100 bytes (which is close to accurate for our file names)
        # then 5,000,000 * 100 bytes = approximately 500 megabytes of memory.
        self.take = 5000000
        self.skip = 0
        self._loading_started = False
    
    def _load_next_page(self):
        """
        Executes the query to get self.take # of rows
        """
        count = self._cursor.execute(self._retrieve_sql % (self.skip, self.take))
        self.skip += self.take
        return count
    
    def _get_row(self):
        """
        Returns all database rows sequentially. Each call to this function
        will return the next row.

        Returns None when all rows in the database have been retrieved.
        otherwise this will return the next row.
        """
        if not self._loading_started:
            self._load_next_page()
            self._loading_started = True

        # Get a row from the database
        row = self._cursor.fetchone()
        # if the row is not None
        if row is not None:
            # then return it
            return row
        else:
            # otherwise attempt to query another chunk
            self._load_next_page()
            # Check the row again
            row = self._cursor.fetchone()
            # if it is not none, return it
            if row is not None:
                return row
            # if it is still none, then there are no more rows.
            return None

    def get_count(self):
        if (self._count_sql != ''):
            self._cursor.execute(self._count_sql)
            result = self._cursor.fetchone()
            return result[0]
        return None

    def get_all(self):
        """
        Generator function for iterating through all files in the database.
        Considering the database can be very large, it is not practical to
        hold all file names in memory at once.
        """

        self.skip = 0
        while True:
            # Get one row from the database
            row = self._get_row()
            # _get_row will return None once we've gone through all rows.
            if row is None:
                break
            yield row