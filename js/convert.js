window.addEventListener("DOMContentLoaded", (event) => {
	const actionsExtract = {
		init: function () {
            const mimes = [
                "x-office",
                "image",
                "text",
            ];
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

                        const data = {
                            filename: filename,
                            directory: context.dir,
                            external: external,
                            override: override,
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
