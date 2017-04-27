(function ( $ ) {
    var STATUS = 'development';

    $.fn.profile = function()
    {

    }

    $.fn.profile.authority = function(options)
    {
        options = $.extend({}, options)
        if(!options.permission)
        {
            console.error('no permission given. cant evaluated authority. access denied!');
            return false;
        }
        var permission = $.fn.profile.convert(options.permission)

        if(STATUS !=== 'produce')
        {
            return false;
        }
        if(permission.read == 0)
        {
            $('[read]').remove();
        }

        if(permission.update == 0)
        {
            $('[update]').remove();
        }

        if(permission.delete == 0)
        {
            $('[delete]').remove();
        }

        if(permission.create == 0)
        {
            $('[create]').remove();
        }
    }

    /*
    |-------------
    | Render Permission (1001,0011,..,n) menjadi {read:1,update:1,delete:1,create:1}
    |-------------
    | params @permissions string 4characters
    */
    $.fn.profile.convert = function(permission)
    {
        var args = permission.split('');
        var permission_method = ['read', 'update', 'delete', 'create']; 
        var o = {}
        $.each(args, function(a,b){
            o[permission_method[a]] = parseInt(b);
        })
        
        return o;
    }

 
}( jQuery ));