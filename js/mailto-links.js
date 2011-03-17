/* WP Mailto Links Plugin */
(function( w ){

	w.wpml = function ( s, d, e ) {
		w.open( 'mailto:'+ rot13( rev( s ) ) ).close();
	};

	function rev( s ){
		return s.split( '' ).reverse().join( '' );
	};

	function rot13( s ){
		// source: http://jsfromhell.com/string/rot13
		return s.replace( /[a-zA-Z]/g, function( c ) {
			return String.fromCharCode((c <= "Z" ? 90 : 122) >= (c = c.charCodeAt(0) + 13) ? c : c - 26);
		});
	};

})( window );
