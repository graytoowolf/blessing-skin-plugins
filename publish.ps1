if (!(Test-Path updated.json)) {
    exit
}

git config --global user.name 'Pig Fang'
git config --global user.email 'g-plane@hotmail.com'

$token = $env:GH_TOKEN

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
