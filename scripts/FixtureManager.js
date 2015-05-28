var FixtureManager = {
	// Settings
	s: {
		createButton:	jQuery('a#gen-test-data'),
		destroyButton:	jQuery('a#delete-test-data'),
		formNonce:		jQuery('form.lib-form input#wp_lib_ajax_nonce').val(),
		messageDiv:		jQuery('div#fixture-process-messages'),
		numItems:		15,
		numMembers:		15,
		activeRequest:	false,
		stopRequest:	false
	},
	
	init: function() {
		console.log('Init called');
		this.bindUIActions();
	},
	
	bindUIActions: function() {
		this.s.createButton.on('click', function() {
			FixtureManager.createFixtures();
		});
		
		this.s.destroyButton.on('click', function() {
			FixtureManager.destroyFixtures();
		});
	},
	
	displayMessage: function(message) {
		FixtureManager.s.messageDiv.append(jQuery('<p/>', {html: message}));
	},
	
	createFixtures: function() {
		FixtureManager.s.messageDiv.empty();
		
		FixtureManager.recursiveStageIncrementer({
			'api_request'		: 'generate-fixtures',
			'wp_lib_ajax_nonce'	: FixtureManager.s.formNonce,
			'item_count'		: FixtureManager.s.numItems,
			'member_count'		: FixtureManager.s.numMembers
		});
	},
	
	destroyFixtures: function() {
		FixtureManager.s.messageDiv.empty();
		
		FixtureManager.recursiveStageIncrementer({
			'api_request'		: 'delete-fixtures',
			'wp_lib_ajax_nonce'	: FixtureManager.s.formNonce
		});
	},
	
	recursiveStageIncrementer: function(params) {
		wp_lib_api_call(params, function(serverResult) {
			// If request succeeded
			if (serverResult[0] === 4) {
				// Display all messages about the stage just completed
				serverResult[1][2][1].forEach(function(message) {
					FixtureManager.displayMessage(message);
				});
				
				if (typeof serverResult[1][2][0] === 'string') {
					if (FixtureManager.s.stopRequest) {
						FixtureManager.displayMessage('Process cancelled');
						return;
					}
					
					FixtureManager.recursiveStageIncrementer({
						api_request			: params.api_request,
						wp_lib_ajax_nonce	: params.wp_lib_ajax_nonce,
						stage_code			: serverResult[1][2][0]
						
					});
				} else if (serverResult[1][2][0] === false) {
					return;
				} else {
					FixtureManager.displayMessage('Server returned invalid code to start next process');
				}
			}
		});	
	}
};
