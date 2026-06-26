<?php
session_start();
if (empty($_SESSION['authenticated'])) {
    header('Location: ../control.php');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=2">
<title>File Manager</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.66/css/elfinder.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;overflow:hidden;background:#0f172a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
#elfinder{height:100%}
#toolbar-top{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:rgba(30,41,59,.8);backdrop-filter:blur(12px);border-bottom:1px solid rgba(148,163,184,.08);gap:12px;flex-wrap:wrap}
#toolbar-top .title{font-size:15px;font-weight:600;color:#e2e8f0;letter-spacing:-.3px}
#site-selector{padding:7px 14px;border:1px solid rgba(51,65,85,.6);border-radius:8px;background:rgba(15,23,42,.8);color:#e2e8f0;font-size:13px;outline:none;cursor:pointer;font-family:inherit;min-width:160px}
#site-selector:focus{border-color:#3b82f6}
#site-selector option{background:#1e293b;color:#e2e8f0}
@media(max-width:600px){#toolbar-top{padding:8px 12px}#site-selector{min-width:120px;font-size:12px}}
</style>
</head>
<body>
<div id="toolbar-top">
<span class="title">File Manager</span>
<select id="site-selector">
<option value="">All (Server Root)</option>
</select>
</div>
<div id="elfinder"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.66/js/elfinder.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.66/js/i18n/elfinder.en.js"></script>
<script>
(function() {
    var elfinderInstance = null;
    var volumes = {};

    var initElfinder = function() {
        $('#elfinder').elfinder({
            url: 'connector.php',
            lang: 'en',
            width: '100%',
            height: '100%',
            resizable: false,
            soundPath: 'https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.66/sounds/',
            uiOptions: {
                toolbar: [
                    ['back', 'forward'],
                    ['reload'],
                    ['home', 'up'],
                    ['mkdir', 'mkfile', 'upload'],
                    ['open', 'download', 'getfile'],
                    ['info'],
                    ['quicklook'],
                    ['copy', 'cut', 'paste'],
                    ['rm'],
                    ['duplicate', 'rename', 'edit'],
                    ['extract', 'archive'],
                    ['search'],
                    ['view'],
                    ['help']
                ],
                tree: {
                    renderTree: function() {
                        var tree = this;
                        tree.$tree.on('click', '.elfinder-navbar-root', function() {
                            var volId = $(this).data('volume');
                            if (volId && siteSelector.val() !== volId) {
                                siteSelector.val(volId);
                            }
                        });
                    }
                }
            },
            contextmenu: {
                navbar: ['open', '|', 'copy', 'cut', 'paste', 'duplicate', '|', 'rm', '|', 'info'],
                cwd: ['reload', 'back', '|', 'paste', '|', 'mkdir', 'mkfile', 'upload', '|', 'sort', '|', 'info'],
                files: [
                    'getfile', '|', 'open', 'quicklook', '|', 'download', '|', 'copy', 'cut', 'paste', 'duplicate', '|',
                    'rm', '|', 'edit', 'rename', '|', 'archive', 'extract', '|', 'info'
                ]
            },
            commandsOptions: {
                edit: {
                    mimeTypes: {
                        'text/html': 'Editor',
                        'text/css': 'Editor',
                        'text/javascript': 'Editor',
                        'application/x-php': 'Editor',
                        'text/x-php': 'Editor'
                    }
                },
                quicklook: {
                    googleDocsMimes: ['application/pdf', 'image/tiff', 'application/msword', 'application/vnd.ms-word', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
                    officeOnlineMimes: ['application/vnd.ms-office', 'application/msword', 'application/vnd.ms-word', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.spreadsheet', 'application/vnd.oasis.opendocument.presentation']
                }
            },
            handlers: {
                init: function(event, fm) {
                    elfinderInstance = fm;
                    var roots = fm.option('roots') || [];
                    if (fm.volumeIds) {
                        roots = fm.volumeIds;
                    }
                    if (!roots.length) {
                        $.each(fm.volumes, function(id) {
                            roots.push(id);
                        });
                    }
                    var select = $('#site-selector');
                    select.find('option:gt(0)').remove();
                    var added = {};
                    $.each(roots, function(i, id) {
                        var volData = fm.volume(id);
                        if (volData && volData.name) {
                            var option = $('<option>').val(id).text(volData.name);
                            select.append(option);
                            added[id] = true;
                        }
                    });
                }
            },
            bootCallback: function(fm, extraObj) {
                fm.bind('init', function() {
                    try {
                        var roots = Object.keys(fm.volumes || {});
                        var select = $('#site-selector');
                        select.find('option:gt(0)').remove();
                        $.each(roots, function(i, id) {
                            var volData = fm.volume(id);
                            if (volData && volData.name) {
                                select.append($('<option>').val(id).text(volData.name));
                            }
                        });
                    } catch(e) {}
                });
            }
        });
    };

    var siteSelector = $('#site-selector');

    siteSelector.on('change', function() {
        var volId = $(this).val();
        if (!volId) {
            if (elfinderInstance) {
                elfinderInstance.exec('open', elfinderInstance.volume(elfinderInstance.volumeIds[0])?.hash);
            }
            return;
        }
        if (elfinderInstance) {
            var target = elfinderInstance.volume(volId);
            if (target && target.hash) {
                elfinderInstance.exec('open', target.hash);
            } else {
                elfinderInstance.exec('open', volId);
            }
        }
    });

    $(document).ready(function() {
        initElfinder();
    });
})();
</script>
</body>
</html>
