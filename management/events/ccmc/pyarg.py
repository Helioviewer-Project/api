# RawTextHelpFormatter allows PROGRAM_DESCRIPTION to be a multiline str.
# Without passing this to argparse, it will print PROGRAM_DESCRIPTION to stdout all on one line even if it has newline characters.
from argparse import RawTextHelpFormatter
from argparse import ArgumentParser

# Reference: https://docs.python.org/3/library/argparse.html
# description - The program description, may be a multiline string
# args - A list of arguments to be passed to parser.add_argument in the format
#        ([positional_args], {keyword_args: value})
#        these are passed directly to parser.add_argument
def parse_args(description: str, args: list):
    parser = ArgumentParser(description=description, formatter_class=RawTextHelpFormatter)
    for arg in args:
        parser.add_argument(*arg[0], **arg[1])
    return parser.parse_args()