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
                <ul id="nav-content" class="nav flex-column">
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
                    <div class="table-item col-md-12">
                        <p class="table-title">
                            <span class="btn btn-xs btn-info">请求参数</span>
                        </p>
                        <table id="paramRequird" class="table">
                            <tr>
                                <td>参数</td>
                                <td>类型</td>
                                <td>描述</td>
                                <td>默认值</td>
                                <td>是否必须</td>
                            </tr>
                        </table>
                    </div>
                    <div class="table-item col-md-12">
                        <p class="table-title">
                            <span class="btn btn-xs btn-info">返回参数</span>
                        </p>
                        <table id="returnRequird" class="table">
                            <tr>
                                <td>参数</td>
                                <td>类型</td>
                                <td>描述</td>
                                <td>默认值</td>
                                <td>是否必须</td>
                            </tr>
                        </table>
                    </div>
                    <div class="table-item col-md-12">
                        <p class="table-title">
                            <span class="btn btn-xs btn-info">状态码说明</span>
                        </p>
                        <table id="codeRequird" class="table">
                        </table>
                    </div>
                </div>
                <div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="inputAddress">Params（json 数组）</label>
                        <div id="editor" style="height: 300px">{{$params}}</div>
                        <input type="hidden" name="params" id="params" value="{{$params}}">
                    </div>
                </div>
                <button id="submit-btn" type="submit" class="btn btn-primary">Request</button>
            </form>
            <div class="row col-md-12">
                @if( !empty($error) )
                    <div id='alert' class="alert alert-danger" role="alert" style="width: 100%">
                        code&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: {{$error['code']}} <br>
                        message : {{$error['message']}}
                    </div>
                    @if($error['resp'])
                        <h5>返回内容:</h5>
                        <iframe style="width: 100%;height: 500px;border: none;"
                                srcdoc='{{$error['resp']->getBody()}}'></iframe>
                        <hr>
                    @endif
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
<script type="text/javascript">
    var editor = ace.edit("editor");
    var params = <?php echo $params; ?>;
    var data = <?php echo $data; ?>;
    var error_empty = <?php echo isset($error) ? 0 : 1; ?>;
    console.log(params)
    editor.setTheme("ace/theme/monokai");
    editor.session.setMode("ace/mode/json");
    editor.on('change', function (e) {
        $('#params').val(editor.getValue())
    })
    $(document).ready(function(){
        intTable();
        var valKey =$("#method option:first-child").text();
        var data = <?php echo $data; ?>;
        var methodArray = data[valKey];
        changeTable(methodArray);
        if (error_empty > 0) {
            var storage = window.localStorage;
            var valKey = $("#method").find("option:selected").text();
            var d = JSON.stringify(params);
            storage.setItem(valKey, d);
        }
        changeNavShow()
    });
    $('#method').on('change', function() {
        var valKey = $("#method").find("option:selected").text();
        var methodArray = data[valKey];
        intTable()
        changeTable(methodArray)
    })
    function intTable() {
        $("#paramRequird").empty();
        $("#returnRequird").empty();
        $("#codeRequird").empty();
        var html1 = "<tr><td>参数</td><td>类型</td><td>描述</td><td>默认值</td><td>是否必须</td></tr>";
        var html2 = "<tr><td>参数</td><td>类型</td><td>描述</td>/tr>";
        var html3 = "<tr><td>状态码</td><td>描述</td></tr>";
        $(html1).appendTo("#paramRequird");
        $(html2).appendTo("#returnRequird");
        $(html3).appendTo("#codeRequird");
    }

    function changeTable(params) {
        params.param.map(function (val, index) {
            var $trTemp = $("<tr></tr>");
            //往行里面追加 td单元格
            $trTemp.append("<td>"+ val.param_name +"</td>");
            $trTemp.append("<td>"+ val.param_type +"</td>");
            $trTemp.append("<td>"+ val.param_title +"</td>");
            $trTemp.append("<td>"+ val.param_default +"</td>");
            $trTemp.append("<td>"+ val.param_require +"</td>");
            $trTemp.appendTo("#paramRequird");
        })
        params.return.map(function (val, index) {
            var $trTemp = $("<tr></tr>");
            //往行里面追加 td单元格
            $trTemp.append("<td>"+ val.return_name +"</td>");
            $trTemp.append("<td>"+ val.return_type +"</td>");
            $trTemp.append("<td>"+ val.return_title +"</td>");
            $trTemp.appendTo("#returnRequird");
        })
        params.code.map(function (val, index) {
            var $trTemp = $("<tr></tr>");
            //往行里面追加 td单元格
            $trTemp.append("<td>"+ val.code +"</td>");
            $trTemp.append("<td>"+ val.content +"</td>");
            $trTemp.appendTo("#codeRequird");
        })
    }
    function changeLocoal(params) {
        var storage=window.localStorage;
        var valKey = $("#method").find("option:selected").text();
        var data=editor.getValue();
        var d=JSON.stringify(data).replaceAll("\r|\n|\\s", "");
        storage.setItem(valKey,d);
    }

    function changeNavShow(){
        var storage=window.localStorage;
        for(var i=0;i<storage.length;i++){
            var key=storage.key(i);
            var $trTemp = $('<li class="nav-item"></li>');
            $trTemp.append('<a class="nav-link">'+ key +'</a>');
            $trTemp.appendTo("#nav-content");
            $trTemp.attr('methond',key)
        }
    }
    $('#nav-content').on('click','.nav-item', function(){
        var activeKey = $(this).attr('methond');
        var param = localStorage.getItem(activeKey)
        $('.nav-item').removeClass('bg-info');
        $(this).addClass('bg-info');
        $("#method").val(activeKey);
        var methodArray = data[activeKey];
        intTable();
        changeTable(methodArray);
        $('#params').val(param);
        editor.setValue(param)
    })
</script>
<script>hljs.initHighlightingOnLoad();</script>
</body>
</html>