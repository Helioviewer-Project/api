import numpy as np
from matplotlib.colors import LinearSegmentedColormap

def gen_rgb(cmap: LinearSegmentedColormap, name: str):
    """
    Creates a text-based colormap using the given colormap
    args:
        - cmap The color map to use to generate the color table
        - name Name of the color table file
    """
    table = np.fromfunction(lambda i, j: cmap(i) * 255, (256,1), dtype=int)
    table = table.reshape((256, 4))
    with open(name, "w") as fp:
        for row in table:
            r, g, b, a = row
            fp.write("%d %d %d\n" % (r, g, b))

if __name__ == "__main__":
    import sys
    import argparse
    parser = argparse.ArgumentParser(
                    prog='Gen RGB',
                    description='Generates text-based RGB color tables for further processing')
    parser.add_argument('name', help="Name of the color table to be created")
    parser.add_argument('cm', help="Name of the color map variable in sunpy.visualization.colormaps.cm")
    args = parser.parse_args()
    import sunpy.visualization.colormaps.cm as cm
    cmap = cm.__dict__[args.cm]
    gen_rgb(cmap, args.name)
