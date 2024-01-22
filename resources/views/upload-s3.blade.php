<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <title>Resumablejs + Laravel Chunk Upload</title>

    {{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/resumable.js/1.1.0/resumable.min.js"></script> --}}
    <script src="{{ asset('js/resumable.js') }}"></script>
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
        chunkSize: 5 * 1024 * 1024, // s3 min part 5MB
        forceChunkSize: true,
        headers: {
            'Accept': 'application/json'
        },
        testChunks: false,
        throttleProgressCallbacks: 1,
    });

    resumable.assignBrowse(browseFile[0]);

    resumable.on('fileAdded', function (file) { // trigger when file picked
        file.parts = [];

        resumable.opts.query.Key = (function () {
            let [basename, ext] = file.fileName.split('.');
            return `${basename}-${Date.now()}.${ext}`;
        })();

        showProgress();
        // resumable.upload() // to actually start uploading.
    });

    resumable.on('chunkingComplete', function (file) {
        console.log(file.chunks);
    });

    resumable.on('fileProgress', function (file, message) { // trigger when file progress update
        if (message && (file.chunks.length > 1)) {
            message = JSON.parse(message);
            file.parts.push(message.part);
        }

        updateProgress(Math.floor(file.progress() * 100));
    });

    resumable.on('fileSuccess', async function (file, message) { // trigger when file upload complete
        if ((file.chunks.length > 1) && (file.chunks.length == file.parts.length)) {
            let url = '{{ route('upload.s3.compelete') }}?' + new URLSearchParams(resumable.opts.query);
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({parts: file.parts}),
            });
            $("#response").text(response);
        } else {
            $("#response").text(JSON.stringify(message, null, 4));
        }

        $('.card-footer').show();
        hideProgress();
    });

    resumable.on('fileError', function (file, message) { // trigger when there is any error
        console.log('error', file, message);
        // alert('file uploading error.');
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