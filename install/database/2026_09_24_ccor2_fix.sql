-- Fix duplicate uiOrder for CCOR-2 (sourceId 133): Detector/Instrument both had uiOrder=2.
UPDATE datasource_property SET uiOrder = 3 WHERE sourceId = 133 AND label = 'Detector';
UPDATE datasource_property SET uiOrder = 4 WHERE sourceId = 133 AND label = 'Measurement';
