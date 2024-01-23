<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <title>Resumablejs + Laravel Chunk Upload</title>
</head>
<body>

<div class="container pt-4" id="app">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header text-center">
                    <h5>Upload File To S3</h5>
                </div>

                <div class="card-body">
                    <div class="text-center">
                        <button ref="browserBtn" class="btn btn-primary">Browse File</button>
                    </div>
                    <div v-if="file">
                        <div>Filename: <span v-text="file.fileName"></span></div>
                        <div>Size: <span v-text="file.size"></span> bytes</div>
                        <div>Chunks:
                            <ol>
                                <li v-for="chunk in file.chunks">
                                    <div>Size: <span v-text="chunk.endByte - chunk.startByte"></span> bytes</div>
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="height: 100%" :style="{width: formatProgress(chunk.progress()) + '%'}">@{{ formatProgress(chunk.progress()) }}%</div>
                                </li>
                            </ol>
                        </div>
                        <div class="progress mt-3" style="height: 25px">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="height: 100%" :style="{width: formatProgress(file.progress()) + '%'}">@{{ formatProgress(file.progress()) }}%</div>
                        </div>
                    </div>
                </div>

                <div class="card-footer p-4" v-show="responses.length">
                    <ul class="list-unstyled mb-0">
                        <li v-for="({status, value}, index) in responses">
                            <div class="alert" :class="`alert-${status}`" role="alert">
                                <pre class="mb-0" v-text="formatJson(value)"></pre>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>


<script src="{{ asset('js/resumable.js') }}"></script>
<script type="module">
    import { createApp } from 'https://unpkg.com/vue@3/dist/vue.esm-browser.js'
    createApp({
        data() {
            return {
                resumable: null,
                responses: [],

                Key: null,
                UploadId: null,
                parts: [],
                file: null,
            };
        },

        mounted() {
            this.initResumable();
        },

        methods: {
            initResumable() {
                let self = this;
                let resumable = new Resumable({
                    target: '{{ route('upload.s3.store') }}',
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
                this.resumable = resumable;

                resumable.assignBrowse(this.$refs.browserBtn);

                resumable.on('fileAdded', async function (file) { // trigger when file picked
                    self.file = file;
                    self.parts = [];

                    await self.retriveQuery(file.fileName);
                    resumable.opts.query = {...resumable.opts.query, ...{
                        Key: self.Key,
                        UploadId: self.UploadId,
                    }};

                    resumable.upload() // to actually start uploading.
                });

                resumable.on('fileProgress', function (file, message) { // trigger when file progress update
                    if (message && (file.chunks.length > 1)) {
                        message = JSON.parse(message);
                        self.parts.push(message.part);
                    }
                    self.responses.push({status: 'info', value: message});
                });

                resumable.on('progress', function (file, message) {
                    self.$forceUpdate();
                });

                resumable.on('fileSuccess', async function (file, message) { // trigger when file upload complete
                    await self.completeMultiPart();
                });

                resumable.on('fileError', function (file, message) { // trigger when there is any error
                    self.responses.push({status: 'danger', value: message});
                });
            },

            retriveQuery: async function(fileName) {
                try {
                    const response = await fetch('{{ route('upload.s3.prepare') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            _token: '{{ csrf_token() }}',
                            filename: fileName,
                        }),
                    });
                    const data = await response.json();
                    this.Key = data.Key;
                    this.UploadId = data.UploadId;
                    this.responses.push({status: 'info', value: data});

                    return data;
                } catch (error) {
                    this.responses.push({status: 'danger', value: error});
                }
            },
            completeMultiPart: async function() {
                if (this.file.chunks.length <= 1) return;
                if (this.file.chunks.length != this.parts.length) return;

                try {
                    const response = await fetch('{{ route('upload.s3.complete') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            _token: '{{ csrf_token() }}',
                            Key: this.Key,
                            UploadId: this.UploadId,
                            parts: this.parts,
                        }),
                    });
                    const data = await response.json();
                    this.responses.push({status: 'info', value: data});

                    return data;
                } catch (error) {
                    this.responses.push({status: 'danger', value: error});
                }
            },
            formatJson(json) {
                if (typeof json === 'string') {
                    json = JSON.parse(json);
                }
                return JSON.stringify(json, null, 4);
            },
            formatProgress(val) {
                return Math.ceil(val * 100);
            },
        },
    }).mount('#app');
</script>

</body>
</html>