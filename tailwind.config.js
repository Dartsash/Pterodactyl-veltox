// Veltox: keeps whatever config the panel/Blueprint ships (tailwind.base.config.js)
// and merges the theme palette from tailwind.veltox-palette.js on top of it, so
// classes like bg-danger-500/15 and text-primary-400 keep resolving.
const base = require('./tailwind.base.config.js');
const palette = require('./tailwind.veltox-palette.js');

base.theme = base.theme || {};
base.theme.extend = base.theme.extend || {};

for (const [group, values] of Object.entries(palette)) {
    base.theme.extend[group] = Object.assign({}, base.theme.extend[group], values);
    // A base config that sets theme.<group> directly (not under extend) would
    // otherwise wipe out the palette, so merge there as well when present.
    if (base.theme[group] && group !== 'extend') {
        base.theme[group] = Object.assign({}, base.theme[group], values);
    }
}

module.exports = base;
