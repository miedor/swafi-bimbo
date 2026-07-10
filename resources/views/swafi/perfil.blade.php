@extends('layouts.app')

@section('title', 'Mi perfil | SWAFI')
@section('page_title', 'Mi perfil')
@section('page_subtitle', 'Datos de usuario, avatar y seguridad de la sesión')
@section('breadcrumb', 'Mi perfil')

@section('page_styles')
<style>
  .profile-grid {
    display: grid;
    grid-template-columns: 0.85fr 1.15fr;
    gap: 18px;
    align-items: start;
  }

  .profile-card-center {
    display: grid;
    justify-items: center;
    gap: 14px;
    text-align: center;
  }

  .profile-avatar-large {
    width: 148px;
    height: 148px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border: 1px solid #dbe7f6;
    border-radius: 34px;
    background: #eef6ff;
    color: #174f9a;
    box-shadow: 0 18px 38px rgba(15, 23, 42, .10);
  }

  .profile-avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .profile-avatar-large svg {
    width: 72px;
    height: 72px;
  }

  .profile-name {
    color: #12345c;
    font-size: 22px;
    font-weight: 950;
    line-height: 1.12;
  }

  .profile-user {
    color: #64748b;
    font-size: 13px;
    font-weight: 850;
  }

  .profile-role-list {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    justify-content: center;
  }

  .profile-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .profile-form-grid label {
    display: block;
  }

  .profile-form-grid span {
    display: block;
    margin-bottom: 6px;
    color: #1d3558;
    font-size: 12px;
    font-weight: 900;
  }

  .profile-form-grid input {
    width: 100%;
    min-height: 40px;
    padding: 9px 11px;
    border: 1px solid #d5e1ef;
    border-radius: 12px;
    background: #ffffff;
    color: #16304d;
    font-size: 13px;
  }

  .profile-field-wide {
    grid-column: 1 / -1;
  }

  .profile-help {
    margin-top: 12px;
    padding: 11px 13px;
    border: 1px solid #d9e6f7;
    border-radius: 14px;
    background: #f8fbff;
    color: #36557a;
    font-size: 12px;
    line-height: 1.45;
  }

  .profile-message {
    margin-bottom: 14px;
    padding: 12px 14px;
    border-radius: 14px;
    font-size: 13px;
    font-weight: 800;
  }

  .profile-message-success {
    border: 1px solid #b9e5bf;
    background: #e8f7ea;
    color: #1f6b2a;
  }

  .profile-message-error {
    border: 1px solid #f2baba;
    background: #fdeaea;
    color: #8a1f1f;
  }

  @media (max-width: 980px) {
    .profile-grid,
    .profile-form-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
@endsection

@section('content')

@if (session('success'))
  <div class="profile-message profile-message-success">
    {{ session('success') }}
  </div>
@endif

@if ($errors->any())
  <div class="profile-message profile-message-error">
    <strong>Se encontraron errores:</strong>
    <ul style="margin:6px 0 0 18px">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<section class="profile-grid">
  <div class="card profile-card-center">
    <div class="profile-avatar-large">
      @if($user->avatar_path)
        <img src="{{ route('perfil.avatar', ['v' => session('swafi_avatar_version', time())]) }}" alt="Avatar de {{ $user->name }}">
      @else
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20 21a8 8 0 0 0-16 0"></path>
          <path d="M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"></path>
        </svg>
      @endif
    </div>

    <div>
      <div class="profile-name">{{ $user->name }}</div>
      <div class="profile-user">{{ $user->usuario ?: $user->email }}</div>
    </div>

    <div class="profile-role-list">
      @forelse($roles as $rol)
        <span class="pill ok">{{ $rol }}</span>
      @empty
        <span class="pill warn">Sin rol asignado</span>
      @endforelse
    </div>

    @if($user->avatar_path)
      <form method="POST" action="{{ route('perfil.avatar.destroy') }}" onsubmit="return confirm('¿Deseas eliminar la imagen de perfil?');">
        @csrf
        @method('DELETE')
        <button class="tab" type="submit">Eliminar avatar</button>
      </form>
    @endif
  </div>

  <div class="card">
    <div class="section-title">
      <h2>Actualizar perfil</h2>
      <span class="pill ok">Perfil personalizado</span>
    </div>

    <form method="POST" action="{{ route('perfil.update') }}" enctype="multipart/form-data">
      @csrf

      <div class="profile-form-grid">
        <label>
          <span>Nombre mostrado</span>
          <input name="name" value="{{ old('name', $user->name) }}" required>
        </label>

        <label>
          <span>Usuario</span>
          <input value="{{ $user->usuario ?: 'Sin usuario' }}" disabled>
        </label>

        <label>
          <span>Correo electrónico</span>
          <input value="{{ $user->email }}" disabled>
        </label>

        <label>
          <span>Estatus</span>
          <input value="{{ ucfirst($user->estatus ?? 'activo') }}" disabled>
        </label>

        <label class="profile-field-wide">
          <span>Imagen de perfil o avatar</span>
          <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        </label>
      </div>

      <div class="action-group" style="margin-top:14px">
        <button class="tab" type="submit">Guardar perfil</button>
        <a class="tab" href="{{ route('dashboard') }}">Volver al Dashboard</a>
      </div>
    </form>

    <div class="profile-help">
      La imagen se almacena en el repositorio privado de la aplicación y se muestra únicamente a usuarios autenticados.
      Formatos permitidos: JPG, JPEG, PNG o WEBP. Tamaño máximo: 2 MB.
      Por seguridad, la sesión se cierra automáticamente después de 10 minutos sin actividad.
    </div>
  </div>
</section>

@endsection
