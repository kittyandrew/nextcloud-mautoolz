window.addEventListener("DOMContentLoaded", (event) => {
	const actionsExtract = {
		init: function () {
            const mimes_mautoolz_converter = [
                "application/vnd.lotus-wordpro",
	            "application/vnd.ms-excel",
	            "application/vnd.ms-powerpoint",
	            // "application/vnd.ms-powerpoint.addin.macroEnabled.12",
	            // "application/vnd.ms-powerpoint.presentation.macroEnabled.12",
	            // "application/vnd.ms-powerpoint.slideshow.macroEnabled.12",
	            // "application/vnd.ms-powerpoint.template.macroEnabled.12",
	            // "application/vnd.ms-visio.drawing.macroEnabled.12"
	            "application/vnd.ms-visio.drawing",
	            // "application/vnd.ms-visio.stencil.macroEnabled.12",
	            "application/vnd.ms-visio.stencil",
	            // "application/vnd.ms-visio.template.macroEnabled.12",
	            "application/vnd.ms-visio.template",
	            // "application/vnd.ms-word.document.macroEnabled.12",
	            // "application/vnd.ms-word.template.macroEnabled.12",
	            "application/vnd.oasis.opendocument.presentation",
	            "application/vnd.oasis.opendocument.presentation-template",
	            "application/vnd.oasis.opendocument.spreadsheet",
	            "application/vnd.oasis.opendocument.spreadsheet-template",
	            "application/vnd.oasis.opendocument.text",
	            "application/vnd.oasis.opendocument.text-master",
	            "application/vnd.oasis.opendocument.text-template",
	            "application/vnd.oasis.opendocument.text-web",
	            "application/vnd.openxmlformats-officedocument.presentationml.presentation",
	            "application/vnd.openxmlformats-officedocument.presentationml.slideshow",
	            "application/vnd.openxmlformats-officedocument.presentationml.template",
	            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
	            "application/vnd.openxmlformats-officedocument.spreadsheetml.template",
	            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
	            "application/vnd.openxmlformats-officedocument.wordprocessingml.template",
	            "application/vnd.visio",
	            "application/vnd.wordperfect",
                "application/msword",
                "application/msexcel",
                "application/msonenote",
                "application/mspowerpoint",
                "image",
                "text",
                "text/plain",
                "x-office",
            ];
            const mimes_mautoolz = [
                "application/epub+zip",
            ];
            const mimes = mimes_mautoolz_converter.concat(mimes_mautoolz);
            mimes.forEach(mime => {
			    OCA.Files.fileActions.registerAction({
				    name: 'convertToPDF',
				    displayName: 'Convert to pdf',
				    mime: mime,
				    permissions: OC.PERMISSION_UPDATE,
				    type: OCA.Files.FileActions.TYPE_DROPDOWN,
				    iconClass: 'icon-edit',
				    actionHandler: function (filename, context) {
                        const override = false;
                        const external = context.fileInfoModel.attributes.mountType === "external";
                        let worker;
                        if (mimes_mautoolz_converter.includes(mime)) {
                            worker = "mautoolz-converter";
                        } else if (mimes_mautoolz.includes(mime)) {
                            worker = "mautoolz";
                        } else {
                            console.log("Fatal error: Impossible mime type in Mautoolz Converter");
                        }

                        const data = {
                            filename: filename,
                            directory: context.dir,
                            external: external,
                            override: override,
                            worker: worker,
                            shareOwner: context.fileList.dirInfo.shareOwnerId,
                            mtime: external ? context.fileInfoModel.attributes.mtime : 0,
                        };
                        // Show file processing
                        const tr = context.fileList.findFileEl(filename);
                        context.fileList.showFileBusyState(tr, true);
                        // Make request to compress 
                        $.ajax({
                            type: "POST",
                            async: "true",
                            url: OC.filePath('mautoolz', 'api','convertToPDF.php'),
                            data: data,
                            beforeSend: function() {
                                //document.getElementById("buttons").setAttribute('style', 'display: none !important');
                            },
                            success: function(element) {
                                element = element.replace(/null/g, '');
                                console.log(element);
                                response = JSON.parse(element);
                                if(response.code){
                                    const filesClient = OC.Files.getClient();
                                    if (override){
                                        filesClient.remove(context.dir+"/"+filename);
                                    }
                                    context.fileList.reload();
                                } else {
                                    context.fileList.showFileBusyState(tr, false);
                                    OC.dialogs.alert(
                                        t('mautoolz', response.desc),
                                        t('mautoolz', `Error compressing ${filename}`)
                                    );
                                }
                            }
                        });
                    }
                });
            });
        }
    }
    console.log("Loaded Mautoolz conversion js..");
	actionsExtract.init();
});
