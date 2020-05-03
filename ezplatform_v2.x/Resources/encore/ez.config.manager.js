const path = require('path');

module.exports = (eZConfig, eZConfigManager) => {
    eZConfigManager.add({
        eZConfig,
        entryName: 'ezplatform-page-builder-config-css',
        newItems:[path.resolve(__dirname, '../public/scss/pagebuilder-profiler-block.scss')],
    });
};
