<div class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $managedUser->name ?? '') }}" required autocomplete="name" class="block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 @error('name') border-rose-400 @enderror">
        @error('name')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email', $managedUser->email ?? '') }}" required autocomplete="email" class="block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 @error('email') border-rose-400 @enderror">
        @error('email')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
    </div>
    @if ($creating)
        <div>
            <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Temporary password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 @error('password') border-rose-400 @enderror">
            @error('password')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-700">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15">
        </div>
    @endif
    <div class="sm:col-span-2">
        <label for="role" class="mb-2 block text-sm font-semibold text-slate-700">Global TaskFlow Role</label>
        @php($selectedRole = old('role', isset($managedUser) ? $managedUser->roles->first()?->name : 'member'))
        <select id="role" name="role" required class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 @error('role') border-rose-400 @enderror">
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected($selectedRole === $role->value)>{{ ucwords(str_replace('_', ' ', $role->value)) }}</option>
            @endforeach
        </select>
        <p class="mt-2 text-xs leading-5 text-slate-500">This changes the user's global TaskFlow access. It never changes their roles inside individual projects.</p>
        @error('role')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
    </div>
</div>
