# All-in-one script to create color tables for new Helioviewer images
usage() {
    echo "Usage: ./gen_rgb.sh name sunpy_fn sunpy_fn_args"
    echo ""
    echo "Creates color tables for use in Helioviewer"
    echo "See https://github.com/sunpy/sunpy/blob/main/sunpy/visualization/colormaps/color_tables.py for available functions"
}
if [ $# -lt 3 ]; then
    usage
    exit
fi

name=$1
sunpy_fn=$2
shift; shift
sunpy_args=$@

python gen_rgb.py $name $sunpy_fn -a $sunpy_args
php gen_color_table.php $name