<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <title>Resumablejs + Laravel Chunk Upload</title>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/resumable.js/1.1.0/resumable.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body>

<div class="container pt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header text-center">
                    <h5>Upload File</h5>
                </div>

                <div class="card-body">
                    <div id="upload-container" class="text-center">
                        <button id="browseFile" class="btn btn-primary">Browse File</button>
                    </div>
                    <div style="display: none" class="progress mt-3" style="height: 25px">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="width: 75%; height: 100%">75%</div>
                    </div>
                </div>

                <div class="card-footer p-4" style="display: none">
                    <div id="response"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    let browseFile = $('#browseFile');
    let resumable = new Resumable({
        target: '{{ route('upload.s3') }}',
        query: {_token: '{{ csrf_token() }}'},
        // fileType: ['png', 'jpg', 'jpeg', 'mp4'],
        chunkSize: 1.5 * 1024 * 1024, // default is 1*1024*1024, this should be less than your maximum limit in php.ini
        // simultaneousUploads: 1,
        headers: {
            'Accept': 'application/json'
        },
        testChunks: false,
        throttleProgressCallbacks: 1,
    });

    resumable.assignBrowse(browseFile[0]);

    resumable.on('fileAdded', function (file) { // trigger when file picked
        console.log('fileAdded');
        showProgress();
        resumable.upload() // to actually start uploading.
    });

    resumable.on('fileProgress', function (file) { // trigger when file progress update
        console.log('fileProgress', file);
        updateProgress(Math.floor(file.progress() * 100));
    });

    resumable.on('fileSuccess', function (file, response) { // trigger when file upload complete
        console.log('fileSuccess');
        response = JSON.parse(response)

        $("#response").text(JSON.stringify(response, null, 4));

        $('.card-footer').show();
        hideProgress();
    });

    resumable.on('fileError', function (file, response) { // trigger when there is any error
        console.log('error', file, response);
        // alert('file uploading error.');
    });


    resumable.on('uploadStart', function () {
        console.log('uploadStart');
    });
    resumable.on('complete', function () {
        console.log('complete');
    });
    resumable.on('progress', function () {
        console.log('progress');
    });
    resumable.on('chunkingStart', function (file) {
        console.log('chunkingStart', file);
    });
    resumable.on('chunkingProgress', function (file, ratio) {
        console.log('chunkingProgress', file, ratio);
    });
    resumable.on('chunkingComplete', function (file) {
        console.log('chunkingProgress', file);
    });


    let progress = $('.progress');

    function showProgress() {
        progress.find('.progress-bar').css('width', '0%');
        progress.find('.progress-bar').html('0%');
        progress.find('.progress-bar').removeClass('bg-success');
        progress.show();
    }

    function updateProgress(value) {
        progress.find('.progress-bar').css('width', `${value}%`)
        progress.find('.progress-bar').html(`${value}%`)

        if (value === 100) {
            progress.find('.progress-bar').addClass('bg-success');
        }
    }

    function hideProgress() {
        progress.hide();
    }
</script>

</body>
</html>