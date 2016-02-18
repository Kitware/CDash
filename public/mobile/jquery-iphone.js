var cdashUser = undefined;
jQuery.user = function() {
    if (jQuery.cookie('loginname') == undefined ||
        jQuery.cookie('id_hash') == undefined) {
        return {
            isLoggedIn : false
        };
    }

    if (cdashUser == undefined) {
        jQuery.ajax({
            url : '/services',
            type : 'POST',
            dataType : 'json',
            async : false,
            data : {
                endPoint : '/user/' + jQuery.cookie('loginname')
            },
            success : function(json) {
                json.users[0].isLoggedIn = true;
                cdashUser = json.users[0];
            }
        });
    }

    return cdashUser;
};

jQuery.writeOp = function(params) {
    if (typeof(params.output) == 'undefined') {
        params.output = 'json';
    }

    var url = '/ajax/' + params.module + '/' + params.method + '.' + params.output;
    jQuery.ajax({
        'url' : url,
        'type' : 'POST',
        'dataType' : params.output,
        'data' : params.data,
        'success' : function(resp) {
            return params.success(resp);
        },
        'error' : function(xml) {
            var json = eval('(' + xml.responseText + ')');
            json.http = {
                'code' : xml.status,
                'status' : xml.statusText
            };

            return params.error(json);
        }
    });
};
