@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <main class="relative isolate grid min-h-screen overflow-hidden lg:grid-cols-[minmax(0,1.15fr)_minmax(420px,0.85fr)]">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_18%_18%,rgba(99,102,241,0.22),transparent_30%),radial-gradient(circle_at_72%_78%,rgba(14,165,233,0.12),transparent_28%),linear-gradient(135deg,#020617_0%,#0f172a_55%,#172554_100%)]"></div>

        <section class="relative hidden px-12 py-14 lg:flex lg:flex-col lg:justify-between xl:px-20">
            <div>
                <div class="inline-flex items-center gap-3 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-slate-200 backdrop-blur">
                    <span class="grid size-7 place-items-center rounded-lg bg-indigo-500 text-xs font-black text-white">T</span>
                    TaskFlow workspace
                </div>

                <div class="mt-20 max-w-xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-indigo-300">Work, clarified</p>
                    <h1 class="mt-5 text-5xl font-semibold tracking-tight text-white xl:text-6xl">Plan work. Track progress. Deliver together.</h1>
                    <p class="mt-7 max-w-lg text-lg leading-8 text-slate-300">A focused home for teams to organise projects, keep ownership clear, and move meaningful work forward.</p>
                </div>
            </div>

            <ul class="grid max-w-2xl grid-cols-3 gap-4 text-sm text-slate-200" aria-label="TaskFlow benefits">
                <li class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur"><span class="mb-4 grid size-9 place-items-center rounded-xl bg-indigo-500/20 font-semibold text-indigo-200">01</span>Projects and tasks in one workspace</li>
                <li class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur"><span class="mb-4 grid size-9 place-items-center rounded-xl bg-sky-500/20 font-semibold text-sky-200">02</span>Clear ownership and progress</li>
                <li class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur"><span class="mb-4 grid size-9 place-items-center rounded-xl bg-violet-500/20 font-semibold text-violet-200">03</span>Team activity in one place</li>
            </ul>
        </section>

        <section class="flex items-center justify-center bg-white/95 px-5 py-10 sm:px-10 lg:bg-white/90 lg:px-12 xl:px-20">
            <div class="w-full max-w-md">
                <div class="mb-10 flex items-center gap-3 lg:hidden">
                    <span class="grid size-10 place-items-center rounded-xl bg-indigo-600 text-sm font-black text-white shadow-lg shadow-indigo-600/25">T</span>
                    <span class="text-lg font-bold tracking-tight text-slate-950">TaskFlow</span>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-2xl shadow-slate-900/10 sm:p-9">
                    <div class="mb-8">
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-indigo-600">TaskFlow</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">Welcome back</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">Sign in to your workspace and continue where you left off.</p>
                    </div>

                    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Email address</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6.75 12 12l9-5.25"/><path d="M3 6.75v10.5A1.75 1.75 0 0 0 4.75 19h14.5A1.75 1.75 0 0 0 21 17.25V6.75"/></svg>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="block w-full rounded-xl border border-slate-300 bg-white py-3 pl-12 pr-4 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 @error('email') border-rose-400 focus:border-rose-500 focus:ring-rose-500/15 @enderror">
                    </div>
                    <x-form-error field="email" />
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Password</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="block w-full rounded-xl border border-slate-300 bg-white py-3 pl-12 pr-4 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 @error('password') border-rose-400 focus:border-rose-500 focus:ring-rose-500/15 @enderror">
                    </div>
                    <x-form-error field="password" />
                </div>

                <label class="flex cursor-pointer items-center gap-3 text-sm font-medium text-slate-600">
                    <input
                        name="remember"
                        type="checkbox"
                        value="1"
                        @checked(old('remember'))
                        class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    >
                    Remember me
                </label>

                        <x-button class="w-full py-3">Sign in to TaskFlow</x-button>
                    </form>
                </div>

                <p class="mt-6 text-center text-xs leading-5 text-slate-500">Use your TaskFlow workspace credentials to continue.</p>
            </div>
        </section>
    </main>
@endsection
