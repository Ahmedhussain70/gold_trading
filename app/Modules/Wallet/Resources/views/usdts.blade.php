@extends('dashboard.layouts.app')

@section('title',$title)

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

    @include('dashboard.components.errors')




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
                                        <th>{{transWord('User')}}</th>
                                        <th>{{transWord('Amount')}}</th>
                                        <th>{{transWord('Message')}}</th>
                                        <th>{{transWord('Is Approval')}}</th>
                                        <th>{{transWord('Approve At')}}</th>
                                        <th>{{transWord('Actions')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($usdts as $index => $usdt)
                                        @if ($usdt->user != null)
                                            <tr  class="row_{{$usdt->id}}">
                                                <td>{{$index + 1}}</td>
                                                <td>
                                                    {{ $usdt->user->name }}
                                                </td>
                                                <td>{{ $usdt->amount }}</td>
                                                <td>{{ $usdt->message }}</td>
                                                <td>
                                                    @if ($usdt->is_approval == 1)
                                                        <i class="fa fa-check"></i>
                                                    @else
                                                        <i class="fa fa-times"></i>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ $usdt->approve_at }}
                                                </td>
                                                <td>
                                                    @if ($usdt->is_approval == 0)
                                                        <a onclick="return confirm('Are You Sure?')" href="{{ route('approve_usdts',['id'=>$usdt->id]) }}" class="btn btn-primary btn-sm">{{ transWord('Approve') }}</a>    
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
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
