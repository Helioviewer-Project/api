# Configuration file for the Sphinx documentation builder.
#
# This file only contains a selection of the most common options. For a full
# list see the documentation:
# https://www.sphinx-doc.org/en/master/usage/configuration.html

# -- Path setup --------------------------------------------------------------

# If extensions (or modules to document with autodoc) are in another directory,
# add these directories to sys.path here. If the directory is relative to the
# documentation root, use os.path.abspath to make it absolute, like shown here.
#
# import os
# import sys
# sys.path.insert(0, os.path.abspath('.'))


# -- Project information -----------------------------------------------------

project = 'Helioviewer API V2'
copyright = '2022, The Helioviewer Project'
author = 'The Helioviewer Project'


# -- General configuration ---------------------------------------------------

# Add any Sphinx extension module names here, as strings. They can be
# extensions coming with Sphinx (named 'sphinx.ext.*') or your custom
# ones.
extensions = [
    'sphinx_rtd_theme',
    'sphinx_rtd_dark_mode',
    'sphinxcontrib.openapi',
]

# sphinxcontrib-openapi's :examples: flag renders both response examples and
# auto-generated request examples in raw HTTP wire format (e.g.
# `GET /foo HTTP/1.1\nHost: example.com`). We want the response examples but
# write request examples by hand in each operation's description, so suppress
# the auto-generated request example.
import sphinxcontrib.openapi.openapi30 as _openapi30
_orig_example = _openapi30._example

def _example_responses_only(media_type_objects, method=None, endpoint=None,
                            status=None, nb_indent=0):
    if method is not None:
        return iter(())
    return _orig_example(media_type_objects, method=method, endpoint=endpoint,
                         status=status, nb_indent=nb_indent)

_openapi30._example = _example_responses_only

# Add any paths that contain templates here, relative to this directory.
templates_path = ['_templates']

# List of patterns, relative to source directory, that match files and
# directories to ignore when looking for source files.
# This pattern also affects html_static_path and html_extra_path.
exclude_patterns = []


# -- Options for HTML output -------------------------------------------------

# The theme to use for HTML and HTML Help pages.  See the documentation for
# a list of builtin themes.
#
html_theme = 'sphinx_rtd_theme'

# Add any paths that contain custom static files (such as style sheets) here,
# relative to this directory. They are copied after the builtin static files,
# so a file named "default.css" will overwrite the builtin "default.css".

html_static_path = ['_static']
html_style = 'css/custom.css'
html_js_files = ['js/new-tab-links.js']

# Default unlabeled code blocks to plain text so URLs aren't auto-detected as
# Python and styled accordingly.
highlight_language = 'text'
