# All-in-one script to create color tables for new Helioviewer images
usage() {
    echo "Usage: ./gen_rgb.sh sunpy_cm output_file"
    echo ""
    echo "Creates color tables for use in Helioviewer"
    echo "See https://github.com/sunpy/sunpy/blob/main/sunpy/visualization/colormaps/cm.py for available color tables"
}
if [ $# -lt 2 ]; then
    usage
    exit
fi

sunpy_cm=$1
name=$2

python gen_rgb.py "$name" $sunpy_cm
php gen_color_table.php "$name"