# Nkom
aptやnpmのような感覚でプラグインを管理出来るツール
# 使い方
プラグインのインストール
```
nkom install example
```
プラグインのアップデート
```
nkom update example
```
プラグインの削除(リポジトリにない場合はプラグインの名前で削除可能)
```
nkom remove example
```
```install```などの代わりに```i``` or ```add```などが使用出来ます。
# リポジトリの管理
リポジトリの追加
```
nkom-repo add [リポジトリのアドレスまたはURL]
```
リポジトリの削除
```
nkom-repo remove [リポジトリのアドレスまたはURL]
```
