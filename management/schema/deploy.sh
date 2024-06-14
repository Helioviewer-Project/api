DOCROOT_DIR=../../docroot
SCHEMA_DIR=$DOCROOT_DIR/schema
# If this script isn't running from the expected location, then crash
# You can manually update the expected paths to make everything work from somewhere else.
if [ ! -d $DOCROOT_DIR ]; then
  echo "docroot is not at $DOCROOT_DIR, are you running this from the correct directory?"
  exit 1
fi

python image_schema.py image_layer.schema.template.json > image_layer.schema.json
mkdir -p $SCHEMA_DIR
cp *.schema.json $SCHEMA_DIR