jQuery(function($){
	var createButton = $('form.lib-form a#gen-test-data'),
	deleteButton = $('form.lib-form a#delete-test-data'),
	messageDisplay = $('<div/>', {
		'class'	: 'progress-notifications'
	}).appendTo($('div#wp-lib-workspace'));
	
	// Progresses through a multi-stage process, displaying the resulting messages after each stage's completion
	function recursiveStageIncrementer(params) {
		wp_lib_api_call( params, function( serverResult ) {
			// If request succeeded
			if (serverResult[0] === 4) {
				// Display all messages about the stage just completed
				serverResult[1][2][1].forEach(function(message) {
					messageDisplay.append($('<p/>', {html : message}));
				});
				
				if (typeof serverResult[1][2][0] === 'string') {
					recursiveStageIncrementer({
						api_request			: params.api_request,
						wp_lib_ajax_nonce	: params.wp_lib_ajax_nonce,
						stage_code			: serverResult[1][2][0]
						
					});
				} else if (serverResult[1][2][0] === false) {
					return;
				} else {
					messageDisplay.append($('<p/>', {html : 'Server returned invalid code to start next process'}));
				}
			}
		});	
	}
	
	createButton.click(function() {
		var stageCode, itemCount, memberCount, params;
		
		itemCount	= 25;
		memberCount	= 15;
		
		recursiveStageIncrementer({
			'api_request'		: 'generate-fixtures',
			'wp_lib_ajax_nonce'	: $('form.lib-form input#wp_lib_ajax_nonce').val(),
			'item_count'		: itemCount,
			'member_count'		: memberCount,
		});
	});
	
	deleteButton.click(function() {
		recursiveStageIncrementer({
			'api_request'		: 'delete-fixtures',
			'wp_lib_ajax_nonce'	: $('form.lib-form input#wp_lib_ajax_nonce').val(),
		});
	});
});
