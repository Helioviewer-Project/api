# Minimal makefile for Sphinx documentation
#

# You can set these variables from the command line, and also
# from the environment for the first two.
SPHINXOPTS    ?=
SPHINXBUILD   ?= sphinx-build
SOURCEDIR     = source
BUILDDIR      = build
# Since sphinx forces an "html" folder with the documentation, we're going to
# move all that content into the docs/v2 folder
TARGET_DIR = ../../docroot/docs/v2
PLAYGROUND_TARGET_DIR = $(TARGET_DIR)/playground

# Put it first so that "make" without argument is like "make help".
help:
	@$(SPHINXBUILD) -M help "$(SOURCEDIR)" "$(BUILDDIR)" $(SPHINXOPTS) $(O)

.PHONY: help Makefile

docs: html
	mkdir -p ${TARGET_DIR}
	cp -r ${BUILDDIR}/html/* ${TARGET_DIR}
	cp openapi.yaml ${TARGET_DIR}
	mkdir -p ${PLAYGROUND_TARGET_DIR}
	cp -r playground/* ${PLAYGROUND_TARGET_DIR}

# Catch-all target: route all unknown targets to Sphinx using the new
# "make mode" option.  $(O) is meant as a shortcut for $(SPHINXOPTS).
%: Makefile
	@$(SPHINXBUILD) -M $@ "$(SOURCEDIR)" "$(BUILDDIR)" $(SPHINXOPTS) $(O)
