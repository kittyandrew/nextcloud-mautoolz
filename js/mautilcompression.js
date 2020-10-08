$(document).ready(function () {
    console.log("Started loading ME..");
	const actionsExtract = {
		init: function () {
			OCA.Files.fileActions.registerAction({
				name: 'compressQuick',
				displayName: 'Quick compress',
				mime: 'application/pdf',
				permissions: OC.PERMISSION_UPDATE,
				type: OCA.Files.FileActions.TYPE_DROPDOWN,
				iconClass: 'icon-edit',
				actionHandler: function (filename, context) {
                    const override = false;
                    const imgQuality = 1;
                    const external = context.fileInfoModel.attributes.mountType === "external";

                    const data = {
                        filename: filename,
                        directory: context.dir,
                        external: external,
                        override: override,
                        imgQuality: imgQuality,
                        shareOwner: external ? null : context.fileList.dirInfo.shareOwnerId,
                        mtime: external ? context.fileInfoModel.attributes.mtime : 0,
                    };
                    // Show file processing
                    const tr = context.fileList.findFileEl(filename);
                    context.fileList.showFileBusyState(tr, true);
                    // Make request to compress 
                    $.ajax({
                        type: "POST",
                        async: "true",
                        url: OC.filePath('mautilcompression', 'api','compressFile.php'),
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
                            }else{
                                context.fileList.showFileBusyState(tr, false);
                                OC.dialogs.alert(
                                    t('mautilcompression', response.desc),
                                    t('mautilcompression', `Error compressing ${filename}`)
                                );
                            }
                        }
                    });
                }
            });
        }
    }
    console.log("Loaded Mautil Compression extension js");
	actionsExtract.init();
});
