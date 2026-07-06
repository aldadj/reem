<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Inscription
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'profile_photo' => 'nullable|image|max:2048',
        ]);

        $path = $request->file('profile_photo') ? $request->file('profile_photo')->store('profiles', 'public') : null;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'profile_photo_path' => $path ? Storage::url($path) : null,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);
    }

    // Connexion
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Identifiants incorrects'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    // Mise à jour de la photo de profil
    public function updateProfile(Request $request)
    {
        $request->validate([
            'profile_photo' => 'required|image|max:2048',
        ]);

        $user = $request->user();

        // Supprimer l'ancienne photo du stockage si elle existe
        if ($user->profile_photo_path) {
            // On extrait le chemin relatif à partir de l'URL
            $oldPath = str_replace(Storage::url(''), '', $user->profile_photo_path);
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('profile_photo')->store('profiles', 'public');
        $user->profile_photo_path = Storage::url($path);
        $user->save();

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'user' => $user
        ]);
    }

    // Suppression de la photo de profil
    public function deleteProfilePhoto(Request $request)
    {
        $user = $request->user();

        if ($user->profile_photo_path) {
            $oldPath = str_replace(Storage::url(''), '', $user->profile_photo_path);
            Storage::disk('public')->delete($oldPath);
            $user->profile_photo_path = null;
            $user->save();
        }

        return response()->json([
            'message' => 'Photo de profil supprimée',
            'user' => $user
        ]);
    }

    public function updateProfileInfo(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $updated = false;

        if ($request->filled('name')) {
            $user->name = $request->name;
            $updated = true;
        }

        if ($updated) {
            $user->save();
        }

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'user' => $user,
        ]);
    }

    public function googleLogin(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $googleClientId = config('services.google.client_id');
        if (empty($googleClientId)) {
            return response()->json([
                'message' => 'Google client ID non configuré sur le serveur.'
            ], 500);
        }

        $googleToken = $request->input('token');
        $googleResponse = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $googleToken,
        ]);

        if ($googleResponse->failed()) {
            return response()->json([
                'message' => 'Jeton Google invalide ou expiré.'
            ], 401);
        }

        $payload = $googleResponse->json();

        if (($payload['aud'] ?? '') !== $googleClientId) {
            return response()->json([
                'message' => 'Jeton Google non valide pour cette application.'
            ], 401);
        }

        $email = $payload['email'] ?? null;
        if (! $email) {
            return response()->json([
                'message' => 'Adresse email Google introuvable.'
            ], 400);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $payload['name'] ?? $email,
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'profile_photo_path' => $payload['picture'] ?? null,
            ]);
        } elseif (! $user->profile_photo_path && ! empty($payload['picture'])) {
            $user->profile_photo_path = $payload['picture'];
            $user->save();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 200);
    }
}