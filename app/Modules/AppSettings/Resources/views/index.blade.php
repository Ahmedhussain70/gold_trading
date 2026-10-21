@extends('dashboard.layouts.app')

@section('title', $title)

@section('content')

    @include('dashboard.components.errors')

    <div class="card card-primary">
        <div class="card-header">
            <h4>{{ $title }}</h4>
        </div>
        <div class="card-body">
            <form action="{{ route('update_appsettings') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="initial_demo_balance">{{ transWord('Initial Demo Balance') }}</label>
                    <input type="number" name="initial_demo_balance" id="initial_demo_balance" class="form-control"
                        value="{{ $settings['initial_demo_balance'] ?? 0 }}" required>
                </div>

                <div class="form-group">
                    <label for="sell_commission_rate">{{ transWord('Sell Commission Rate (%)') }}</label>
                    <input type="number" name="sell_commission_rate" id="sell_commission_rate" class="form-control"
                        value="{{ $settings['sell_commission_rate'] ?? 0 }}" step="0.01" required>
                </div>

                <div class="form-group">
                    <label for="minimum_withdraw">{{ transWord('Minimum Withdraw Amount') }}</label>
                    <input type="number" name="minimum_withdraw" id="minimum_withdraw" class="form-control"
                        value="{{ $settings['minimum_withdraw'] ?? 0 }}" required>
                </div>

                <div class="form-group">
                    <label for="exchange_rate_usd_to_egp">{{ transWord('Exchange Rate (USD to EGP)') }}</label>
                    <input type="number" name="exchange_rate_usd_to_egp" id="exchange_rate_usd_to_egp" 
                        class="form-control" value="{{ $settings['exchange_rate_usd_to_egp'] ?? 50 }}" 
                        step="0.01" min="1" max="1000" required>
                    <small class="form-text text-muted">{{ transWord('1 USD = ? EGP') }}</small>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">{{ transWord('Save Settings') }}</button>
                </div>
            </form>
        </div>
    </div>

@endsection
