<?php

namespace file;
use App\Models\Player;
use App\Models\Texture;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yggdrasil\Exceptions\ForbiddenOperationException;
use Yggdrasil\Models\Token;
use Storage;

class TextureController extends Controller
{
  public function skin(Request $request)
  {
    $authorization = str_replace('Bearer ', '', $request->header('Authorization'));
    $token = Token::find($authorization);
    if ($token && $token->isValid()) {

      $user = User::where('email', $token->owner)->first();

      if ($user->permission == User::BANNED) {
        throw new ForbiddenOperationException(trans('Yggdrasil::exceptions.user.banned'));
      }

      foreach ($user->players as $player);

      if ($player->tid_skin < 1) {
        $skin = Texture::where('tid', 1)->firstOrFail();
        # code...
      } else {
        $skin = Texture::where('tid', $player->tid_skin)->firstOrFail();
      }
      $skins = [
        'id' => $this->scuuid($skin->hash),
        'state' => 'ACTIVE',
        'variant' => 'SLIM',
        'url' => url('/csl/textures') . '/' . $skin->hash
      ];
      if ($player->tid_cape < 1) {
        $capes = [];
      } else {
        $cape = Texture::where('tid', $player->tid_cape)->firstOrFail();
        $capes = [
          'id' => $this->scuuid($cape->hash),
          'state' => 'ACTIVE',
          'alias' => 'Migrator',
          'url' => url('/csl/textures') . '/' . $cape->hash
        ];
      }
      $uuid = uuid::where('name', $player->name)->firstOrFail();
      return stripslashes(json_encode([
        'id' => $uuid->uuid,
        'name' => $player->name,
        'skins' => [$skins],
        'capes' => [$capes],
      ]));
    } else {
      throw new ForbiddenOperationException(trans('Yggdrasil::exceptions.token.invalid'));
    }
  }
  protected function  scuuid($rand)
  {
    $chars = md5($rand);
    $uuid = substr($chars, 0, 8) . '-'
      . substr($chars, 8, 4) . '-'
      . substr($chars, 12, 4) . '-'
      . substr($chars, 16, 4) . '-'
      . substr($chars, 20, 12);
    return $uuid;
  }
}
