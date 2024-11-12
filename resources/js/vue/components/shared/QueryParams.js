export default {
  get: function () {
    const params = {};
    const kv_pairs = window.location.search.substring(1).split('&');
    for (let i = 0; i < kv_pairs.length; i++) {
      const kv = kv_pairs[i].split('=');
      params[kv[0]] = kv[1];
    }
    return params;
  },
};
