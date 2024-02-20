# All-in-one script to create color tables for new Helioviewer images
usage() {
    echo "Usage: ./gen_rgb.sh name sunpy_var"
    echo ""
    echo "Creates color tables for use in Helioviewer"
    echo "See https://github.com/sunpy/sunpy/blob/main/sunpy/visualization/colormaps/cm.py for available options"
}
if [ $# -lt 2 ]; then
    usage
    exit
fi

name=$1
sunpy_var=$2

python gen_rgb.py "${name}_${sunpy_args}" $sunpy_var
php gen_color_table.php "${name}"
