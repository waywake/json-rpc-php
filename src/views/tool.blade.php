<html>
<head>
    <title>Json Rpc Debug Tool</title>
    <link href="https://cdn.bootcss.com/twitter-bootstrap/4.2.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcss.com/highlight.js/9.13.1/styles/ocean.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0">
    <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="#">Json Rpc Debug Tool</a>
    {{--<input class="form-control form-control-dark w-100" type="text" placeholder="Search" aria-label="Search">--}}
    {{--<ul class="navbar-nav px-3">--}}
    {{--<li class="nav-item text-nowrap">--}}
    {{--<a class="nav-link" href="#">Sign out</a>--}}
    {{--</li>--}}
    {{--</ul>--}}
</nav>
<div class="container-fluid">

    <div class="row">

        <nav class="col-md-3 d-none d-md-block bg-light sidebar">
            <div class="sidebar-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="/rpc/doc.html">
                            <span data-feather="home"></span>
                            文档 <span class="sr-only">(current)</span>
                        </a>
                    </li>
                    {{--<li class="nav-item">--}}
                        {{--<a class="nav-link" href="#">--}}
                            {{--<span data-feather="file"></span>--}}
                            {{--abc--}}
                        {{--</a>--}}
                    {{--</li>--}}
                </ul>
            </div>
        </nav>
        <main role="main" class="col-md-8 ml-sm-auto col-lg-9 pt-3 px-4">
            <h2>Request</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="endpoint">Endpoint</label>
                        <input type="text" class="form-control" id="endpoint" placeholder="Endpoint"
                               value="{{$endpoint}}" readonly>
                    </div>
                    {{--<div class="form-group col-md-2">--}}
                    {{--<label for="method">Request Method</label>--}}
                    {{--<select class="form-control" id="method">--}}
                    {{--<option>GET</option>--}}
                    {{--<option>POST</option>--}}
                    {{--</select>--}}
                    {{--</div>--}}
                </div>
                <div class="form-row">

                    <div class="form-group col-md-12">
                        <label for="inputAddress">Method</label>
                        <select class="form-control" id="method" name="method">
                            @foreach($methods as $k => $v)
                                <option @if($method == $k) selected @endif>{{$k}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">

                    <div class="form-group col-md-12">
                        <label for="inputAddress">Params（json 数组）</label>
                        <div id="editor" style="height: 300px">{{$params}}</div>
                        <input type="hidden" name="params" id="params" value="{{$params}}">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Request</button>
            </form>
            <div class="row col-md-12">
                @if( !empty($error) )
                    <div id='alert' class="alert alert-danger" role="alert">
                        RpcServerException: {{$error['message']}} with code {{$error['code']}}
                    </div>
                @endif
                @if( !empty($result) )
                    <h5>Result:</h5>

                    <div class="col-md-12">
                        <pre><code class="json">{{$result}}</code></pre>
                    </div>
                @endif
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.bootcss.com/jquery/3.3.1/jquery.min.js"></script>
<script>window.jQuery || document.write('<script src="https://cdn.bootcss.com/jquery/3.3.1/jquery.slim.js"><\/script>')</script>
<script src="https://cdn.bootcss.com/twitter-bootstrap/4.2.1/js/bootstrap.min.js"></script>
<script src="https://cdn.bootcss.com/highlight.js/9.13.1/highlight.min.js"></script>
<script src="https://cdn.bootcss.com/highlight.js/9.13.1/languages/json.min.js"></script>
<script src="https://cdn.bootcss.com/ace/1.4.2/ace.js"></script>
<script>
    var editor = ace.edit("editor");
    editor.setTheme("ace/theme/monokai");
    editor.session.setMode("ace/mode/json");
    editor.on('change',function(e){
        $('#params').val(editor.getValue())
    })
</script>
<script>hljs.initHighlightingOnLoad();</script>
</body>
</html>