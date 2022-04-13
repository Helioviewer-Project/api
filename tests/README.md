# Testing
This directory is used for unit testing back end Helioviewer Functions
using PHPUnit.

## Legacy Tests
The directory `legacy_tests` contains some older tests that were
written to exercise certain functions, but they do not perform any
assertions on the correctness of the functions. Because of this,
they're useful for reference on how to initialize and execute
functions in a test, but newer tests should be written with PHPUnit
where we can make assertions about the correctness of the code we're
executing.


