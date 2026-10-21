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
                                        <th>{{transWord('Amount (Live)')}}</th>
                                        <th>{{transWord('Amount (Demo)')}}</th>
                                        <th>{{transWord('Current Account')}}</th>
                                        <th>{{transWord('Actions')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($wallets as $index => $wallet)
                                        @if ($wallet->user != null)
                                            <tr  class="row_{{$wallet->id}}">
                                                <td>{{$index + 1}}</td>
                                                <td>{{ $wallet->user->name }}</td>
                                                <td>{{ $wallet->amount }}</td>
                                                <td>{{ $wallet->amount_demo }}</td>
                                                <td>{{ $wallet->current_account }}</td>
                                                <td>
                                                    <a href="{{ route('show_wallets_transactions',['id'=>$wallet->user->id]) }}" class="btn btn-primary btn-sm">{{ transWord('Transactions (Live)') }}</a>
                                                    <a href="{{ route('show_wallets_transactions_demo',['id'=>$wallet->user->id]) }}" class="btn btn-info btn-sm">{{ transWord('Transactions (Demo)') }}</a>
                                                    <button data-user-id="{{ $wallet->user->id }}" data-action="add" class="btn btn-success btn-sm open-wallet-modal">{{ transWord('Add Amount') }}</button>
                                                    <button data-user-id="{{ $wallet->user->id }}" data-action="subtract" class="btn btn-danger btn-sm open-wallet-modal">{{ transWord('Subtract Amount') }}</button>
                                                </td>
                                            </tr>
                                        @endif
                                        <div class="modal fade" id="walletActionModal" tabindex="-1" aria-labelledby="walletActionModalLabel" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="walletActionModalLabel">{{ transWord('Wallet Action') }}</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form id="walletActionForm" method="POST" action="">
                                                        @csrf
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="account_type" class="form-label">{{ transWord('Account Type') }}</label>
                                                                <select id="account_type" name="account_type" class="form-control" required>
                                                                    <option value="live">{{ transWord('Live') }}</option>
                                                                    <option value="demo">{{ transWord('Demo') }}</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="amount" class="form-label">{{ transWord('Amount') }}</label>
                                                                <input type="number" id="amount" name="amount" class="form-control" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="notes" class="form-label">{{ transWord('Notes') }}</label>
                                                                <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ transWord('Close') }}</button>
                                                            <button type="submit" class="btn btn-primary">{{ transWord('Submit') }}</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).on('click', '.open-wallet-modal', function () {
        let userId = $(this).data('user-id');
        let action = $(this).data('action');
        let modal = $('#walletActionModal');

        // تحديث العنوان حسب الإجراء
        let actionTitle = action === 'add' ? '{{ transWord('Add Amount') }}' : '{{ transWord('Subtract Amount') }}';
        modal.find('.modal-title').text(actionTitle);

        // تحديث فورم الإجراء
        let actionUrl = action === 'add' 
            ? "{{ route('wallet.add.amount', ':id') }}" 
            : "{{ route('wallet.subtract.amount', ':id') }}";
        actionUrl = actionUrl.replace(':id', userId);

        $('#walletActionForm').attr('action', actionUrl);

        // عرض المودال
        modal.modal('show');
    });

    // تأكيد قبل الإرسال
    $('#walletActionForm').on('submit', function (e) {
        e.preventDefault();
        let form = $(this);

        Swal.fire({
            title: '{{ transWord('Are you sure?') }}',
            text: '{{ transWord('This action cannot be undone!') }}',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: '{{ transWord('Yes, proceed!') }}'
        }).then((result) => {
            if (result.isConfirmed) {
                form.off('submit').submit();
            }
        });
    });
</script>
@endsection
