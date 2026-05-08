wp.domReady(() => {
	wp.blocks.registerBlockType("test/v2", {
		apiVersion: 2,
		title: "Test Block",
		category: "common",
		edit: () => wp.element.createElement("p", null, "Test Block"),
		save: () => wp.element.createElement("p", null, "Test Block"),
	});

	console.log("Block registered: test/v2");
});
