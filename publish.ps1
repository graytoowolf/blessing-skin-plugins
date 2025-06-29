if (!(Test-Path updated.json)) {
    exit
}

git config --global user.name 'graytoowolf'
git config --global user.email 'graywolf186@gmail.com'

$token = $env:GH_TOKEN
$slug = $env:GH_APP_SLUG

$app = Invoke-RestMethod -Uri "https://api.github.com/users/$slug[bot]"

git config --global user.name $app.login
git config --global user.email "$($app.id)+$($app.login)@users.noreply.github.com"

Set-Location .dist
git add .

$shouldUpdate = git status -s
if ($shouldUpdate) {
  git commit -m "Publish"
  git remote set-url origin "https://tadaf:$token@github.com/graytoowolf/plugins-dist.git"
  git push origin master
}

Set-Location '..'



foreach ($lang in 'en', 'zh_CN') {
    Invoke-WebRequest "https://purge.jsdelivr.net/gh/graytoowolf/plugins-dist@latest/registry_$lang.json"
}
Invoke-WebRequest 'https://purge.jsdelivr.net/gh/graytoowolf/plugins-dist@latest/registry.json'
