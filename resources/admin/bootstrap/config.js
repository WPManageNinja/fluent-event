const config = {};

config.get = function (key, defaultValue = null) {
    if (!key) return { ...this };
    return key.split('.').reduce((obj, k) => obj?.[k], this) ?? defaultValue;
};

export default config;
