export default {
	get: function () {
    var params = {};
    var kv_pairs = window.location.search.substring(1).split("&");
    for (var i = 0; i < kv_pairs.length; i++) {
      var kv = kv_pairs[i].split("=");
      params[kv[0]] = kv[1];
    }
    return params;
	},
}
