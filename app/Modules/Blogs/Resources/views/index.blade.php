@extends('dashboard.layouts.app')

@section('title',$title)

@section('styles')

@endsection

@section('breadcrumb')
    @foreach ($pages as $page)
        <li class="breadcrumb-item active" aria-current="page">
            <a href="{{ $page[1] }}">{{ $page[0] }}</a>
        </li>
    @endforeach
@endsection

@section('content')

<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">{{ $title }}</h6>
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table table-bordered table-striped" id="example">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ transWord('Image') }}</th>
                                <th>{{ transWord('Title') }}</th>
                                <th>{{ transWord('Publish') }}</th>
                                <th>{{ transWord('Actions') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($blogs as $index => $blog)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    @if($blog->image)
                                        <img src="{{ $blog->image }}" style="width:70px;height:70px;" class="img-thumbnail" alt="Blog Image">
                                    @else
                                        <img src="{{ asset('dashboard/assets/images/no-image.png') }}" style="width:70px;height:70px;" class="img-thumbnail" alt="No Image">
                                    @endif
                                </td>
                                <td>{{ Str::substr(($blog->title), 0, 20) }}</td>
                                <td>
                                    @if ($blog->publish == 1)
                                        <span class="badge bg-primary">{{ transWord('Publish') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ transWord('Un Publish') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a class="btn btn-primary btn-sm" href="{{ route('show_blogs',['blog'=>$blog]) }}">{{ transWord('Show') }}</a>
                                    <a class="btn btn-primary btn-sm" href="{{ route('edit_blogs',['blog'=>$blog]) }}">{{ transWord('Edit') }}</a>
                                    <a class="btn btn-danger btn-sm" id="deleteBtn" href="{{ route('destroy_blogs',['blog'=>$blog]) }}" onclick="return confirm('{{ transWord('Are You Sure?') }}')">{{ transWord('Delete') }}</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('javascript')
<script>
    function myFunction() {
        var copyText = document.getElementById("myInput");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);
        alert("Link Copied");
    }
</script>
@endsection