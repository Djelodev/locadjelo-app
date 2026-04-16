@extends('frontend.layouts.app')

@section('content')

    <div class="row">
        <div class="col s12 m6 offset-m3">
            <div class="card">

                <h4 class="center indigo-text uppercase p-t-30">Connexion</h4>

                <div class="p-20">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="row">
                            <div class="input-field col s12">
                                <label for="email">Adresse e-mail</label>
                                <input id="email" type="email" class="{{ $errors->has('email') ? 'is-invalid' : '' }}" name="email" value="{{ old('email') }}" required autofocus>

                                @if ($errors->has('email'))
                                    <span class="helper-text" data-error="wrong" data-success="right">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="row">
                            <div class="input-field col s12">
                                <label for="password">Mot de passe</label>
                                <input id="password" type="password" class="{{ $errors->has('password') ? 'is-invalid' : '' }}" name="password" required>

                                @if ($errors->has('password'))
                                    <span class="helper-text" data-error="wrong" data-success="right">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <p>
                            <label>
                                <input type="checkbox" name="remember" class="filled-in" {{ old('remember') ? 'checked' : '' }} />
                                <span>Se souvenir de moi</span>
                            </label>
                        </p>

                        <div class="row">
                            <div class="input-field col s12">
                                <button type="submit" class="waves-effect waves-light btn indigo">
                                    Se connecter
                                </button>

                                <a class="indigo-text p-l-15" href="{{ route('password.request') }}">
                                    Mot de passe oublié ?
                                </a>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
