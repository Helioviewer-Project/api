-- Add eventState JSON column with default JSON value
ALTER TABLE screenshots ADD COLUMN eventsState JSON NOT NULL DEFAULT '{}' AFTER eventsLabels;

-- Update existing rows to have the eventsState as {}
UPDATE screenshots SET eventsState = '{}' WHERE eventsState IS NULL OR eventsState = '';

-- Add eventState JSON column with default JSON value
ALTER TABLE movies ADD COLUMN eventsState JSON NOT NULL DEFAULT '{}' AFTER eventsLabels;

-- Update existing rows to have the eventsState as {}
UPDATE movies SET eventsState = '{}' WHERE eventsState IS NULL OR eventsState = '';









