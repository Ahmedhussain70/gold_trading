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
                                        <th>{{transWord('User')}}</th>
                                        <th>{{transWord('Status')}}</th>
                                        <th>{{transWord('Actions')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($tickets as $index => $ticket)
                                        <tr>
                                            <td>{{$index + 1}}</td>
                                            <td>{{ $ticket->message }}</td>
                                            <td>{{ $ticket->user->name }}</td>
                                            <td>{{ $ticket->status }}</td>
                                            <td>
                                                <ul style="" class="dtr-details" >
                                                    <li>
                                                        <a href="{{route('responses_tickets',['ticket' => $ticket->id])}}"
                                                           class="btn btn-primary btn-sm">
                                                            <i class="mdi mdi-pencil font-size-14"></i>
                                                            {{ transWord('Responses') }}
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a onclick="return confirm('{{ transWord('Are You Sure?') }}')"  href="{{route('delete_faq_settings',['faq' => $ticket] )}}" class="btn btn-danger btn-sm sa-delete">
                                                            <i class="mdi mdi-trash-can font-size-14"></i>
                                                        </a>
                                                    </li>

                                                </ul>
                                            </td>
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
