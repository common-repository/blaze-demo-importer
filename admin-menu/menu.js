jQuery( document ).ready(function( $ ){
    var systemInfo = BlazeMenuObject.systemInfo
    var systemInfoContainer = $( '.blaze-system-info' )
    systemInfoContainer.on( 'click', '.button-action', function( event ) { 
        var _this = $(this)
        if( _this.hasClass( 'copy-all' ) ) {
            // copy system info in json format
            event.preventDefault()
            navigator.clipboard.writeText( systemInfo )
        } else {
            // download json file
            const file = new Blob( [ JSON.stringify( systemInfo ) ], { type: 'application/json' } );
            _this.attr( 'href', URL.createObjectURL(file) )
            _this.attr( 'download', 'system-info.json' )
            _this.click()
            URL.revokeObjectURL( _this.attr('href') );
        }
    })
}, jQuery)