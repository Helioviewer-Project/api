# API Docs

This directory contains the Helioviewer API documentation source.

## Structure

- `openapi.yaml` — OpenAPI 3.0.3 spec for the full API. **Canonical source of truth** for the endpoint reference.
- `source/api/index.rst` — Sphinx page that renders the API reference from `openapi.yaml` via `sphinxcontrib-openapi`.
- `source/appendix/` — Hand-written RST appendices (data sources, coordinates, event format, events state) and their images. These are *not* in the OpenAPI spec.
- `playground/index.html` — Static Swagger UI page that loads `openapi.yaml` for interactive "Try it out".
- `Makefile` — Builds Sphinx HTML to `../../docroot/docs/v2/` and copies `playground/` to `../../docroot/playground/`.
- `requirements.txt` — Sphinx + sphinxcontrib-openapi (direct deps only, pinned).

## Editing the API reference

Update `openapi.yaml`. Both the Sphinx reference docs and the Swagger UI playground regenerate from it. There are no per-endpoint RST files anymore.

## OpenAPI spec

Covers 25 endpoints across 7 tags:

| Tag | Endpoints |
|-----|-----------|
| JPEG2000 | downloadImage, getJP2Header, getJP2Image, getJPX, getJPXClosestToMidPoint, getStatus |
| Movies | queueMovie, postMovie, reQueueMovie, getMovieStatus, downloadMovie, playMovie |
| Screenshots | takeScreenshot, postScreenshot, downloadScreenshot, getEclipseImage |
| Official Clients | getClosestImage, getDataSources, getTile, shortenURL |
| Solar Features & Events | events |
| YouTube | checkYouTubeAuth, getYouTubeAuth, uploadMovieToYouTube, getUserVideos |
| Website | getNewsFeed |
