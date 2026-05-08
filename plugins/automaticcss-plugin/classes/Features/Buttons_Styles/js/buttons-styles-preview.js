document.addEventListener('DOMContentLoaded', function(event) {
					
	const prefixes = ['bricks-background-', 'bricks-color-'];
	
	const observer = new MutationObserver((mutationsList) => {
		mutationsList.forEach((mutation) => {							
			const nodes = mutation.type === 'childList' ? mutation.addedNodes : [mutation.target];
			
			nodes.forEach((node) => {
				if ( node.nodeType !== Node.ELEMENT_NODE ) { 
					return;
				}

				let has_acss_class = false;
				let elements_to_check = node.classList.contains('bricks-button') ? [node] : node.querySelectorAll('.bricks-button');

				if ( elements_to_check.length == 0 ) {
					return;
				}
				
				for (const element of elements_to_check) {
					for (const className of element.classList) {
						prefixes.forEach((prefix) => {
							if (className.startsWith(prefix)) {
								const suffix = className.substring(prefix.length);
								if ( suffix ) {
									has_acss_class = true;
									if ( !element.classList.contains(suffix) ) {
										element.classList.add(suffix);
									}
								}
								
							}
						});

						if ( has_acss_class == true && element.classList.contains('outline') ) {
							element.classList.remove('outline');
							element.classList.add('btn--outline');
							break;
						}
					}
				}
			});
			
		});
	});

	const targetNode = document.body;

	observer.observe(targetNode, {
		childList: true,
		attributes: true,
		subtree: true
	});
});

