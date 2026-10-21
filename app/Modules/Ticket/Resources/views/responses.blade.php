@extends('dashboard.layouts.app')

@section('title',transWord('All tickets'))

@section('styles')
    <style>
        .modal-dialog-scrollable {
            height: auto !important;
        }
    </style>
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
            <div class="col-12">
                <div class="card">
                    <strong class="card-title">{{ transWord('Add Response') }}</strong>
                    <div class="card-body">
                        <form action="{{ route('add_response_tickets',['ticket'=>$ticket->id]) }}" method="post">
                            @csrf
                            <textarea name="response" id="response" cols="30" rows="10" class="form-control"></textarea>
                            <input type="submit" value="{{ transWord('Save') }}" class="btn btn-primary">
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white d-flex align-items-center justify-content-between border-bottom">
                    <strong class="card-title">{{ $title }}</strong>

                </div>
                <div class="card-body">

                    <div class="row">
                        <div class="col-12">
                            <div class="table-responsive pt-0">
                                <table id="example" class="table table-bordered dt-responsive nowrap"
                                       style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                                    <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>{{transWord('Ticket')}}</th>
                                        <th>{{transWord('Response')}}</th>
                                        <th>{{transWord('Reply At')}}</th>
                                        <th>{{transWord('User')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($responses as $index => $response)
                                        <tr>
                                            <td>{{$index + 1}}</td>
                                            <td>{{ $response->ticket->message }}</td>
                                            <td>{{ $response->response }}</td>
                                            <td>{{ $response->reply_at }}</td>
                                            <td>{{ $response->user }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>

                            </div>
                        </div> <!-- end col -->
                    </div> <!-- end row -->
                </div>
            </div>
        </div> <!-- end col -->
    </div>

@endsection

@section('scripts')

@endsection
