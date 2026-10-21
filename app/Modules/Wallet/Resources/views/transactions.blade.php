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
                        <div class="col-6">
                            <div class="alert alert-success">
                                <h4>{{ transWord('Deposits').' : '.$deposits }}</h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="alert alert-danger">
                                <h4>{{ transWord('Withdraws').' : '.$withdraws }}</h4>
                            </div>
                        </div>
                    </div>

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
                                        <th>{{transWord('Type')}}</th>
                                        <th>{{transWord('Amount Type')}}</th>
                                        <th>{{transWord('Created By')}}</th>
                                        <th>{{transWord('Note')}}</th>
                                        <th>{{transWord('Is Approval')}}</th>
                                        <th>{{transWord('Approve At')}}</th>
                                        <th>{{transWord('Actions')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($transactions as $index => $transaction)
                                            @if ($transaction->user != null)
                                                <tr  class="row_{{$transaction->id}}">
                                                    <td>{{$index + 1}}</td>
                                                    <td>
                                                        {{ $transaction->user->name }}
                                                    </td>
                                                    <td>
                                                        @if ($transaction->type == 'Deposit')
                                                            <i class="fa fa-arrow-up"></i>
                                                            {{ $transaction->credit }}
                                                        @else
                                                            <i class="fa fa-arrow-down"></i>
                                                            -{{ $transaction->debit }}
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="@if($transaction->type == 'Deposit') btn btn-sm btn-success @else btn btn-sm btn-danger @endif">{{ transWord($transaction->type) }}</span>
                                                    </td>
                                                    <td>
                                                        <span class="@if($transaction->amount_type == 'live') btn btn-sm btn-success @else btn btn-sm btn-danger @endif">{{ transWord($transaction->amount_type) }}</span>
                                                    </td>
                                                    <td>
                                                        <span class="@if($transaction->created_by == 'client') btn btn-sm btn-success @else btn btn-sm btn-danger @endif">{{ transWord($transaction->created_by) }}</span>
                                                    </td>
                                                    <td> {{ $transaction->note }} </td>
                                                    <td>
                                                        @if ($transaction->is_approval == 1)
                                                            <i class="fa fa-check"></i>
                                                        @else
                                                            <i class="fa fa-times"></i>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        {{ $transaction->approve_at }}
                                                    </td>
                                                    <td>
                                                        @if ($transaction->is_approval == 0)
                                                            <a onclick="return confirm('Are You Sure?')" href="{{ route('approve_wallets_transactions',['id'=>$transaction->id]) }}" class="btn btn-primary btn-sm">{{ transWord('Approve') }}</a>    
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
