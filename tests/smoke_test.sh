#!/usr/bin/env bash
# 權限與狀態轉換的自動測試
# 用法：先匯入 schema.sql 與 seed.sql 並啟動網站，再執行
#   bash tests/smoke_test.sh http://localhost:8080
set -u
BASE="${1:-http://localhost:8080}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
PASS=0; FAIL=0

check() { # 說明 預期 實際
    if [[ "$3" == *"$2"* ]]; then echo "  ✓ $1"; PASS=$((PASS+1));
    else echo "  ✗ $1（預期含有「$2」，實際：$3）"; FAIL=$((FAIL+1)); fi
}
token() { curl -s -b "$TMP/$1" -c "$TMP/$1" "$BASE/$2" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
login() { # jar 帳號 密碼
    local t; t="$(token "$1" login.php)"
    curl -s -b "$TMP/$1" -c "$TMP/$1" -o /dev/null -w '%{redirect_url}' \
        --data-urlencode "csrf=$t" --data-urlencode "username=$2" --data-urlencode "password=$3" "$BASE/login.php"
}
post() { # jar 路徑 資料...
    local jar="$1" path="$2"; shift 2
    local t; t="$(token "$jar" author/index.php 2>/dev/null)"
    [[ -z "$t" ]] && t="$(token "$jar" editor/index.php)"
    curl -s -b "$TMP/$jar" -c "$TMP/$jar" -o /dev/null -w '%{http_code}' --data-urlencode "csrf=$t" "$@" "$BASE/$path"
}
flash() { curl -s -b "$TMP/$1" -c "$TMP/$1" "$BASE/$2" | grep -o 'alert-[a-z]*">[^<]*' | head -1; }
code() { curl -s -b "$TMP/$1" -o /dev/null -w '%{http_code}' "$BASE/$2"; }

echo "登入"
check "作者登入導向我的投稿" "author/index.php" "$(login alice alice author123)"
check "第二位作者登入" "author/index.php" "$(login bob bob author123)"
check "編輯登入導向待審查清單" "editor/index.php" "$(login editor editor editor123)"
r="$(login nobody alice wrong)"; check "密碼錯誤無法登入" "停留在登入頁" "$([[ -z "$r" ]] && echo 停留在登入頁 || echo "導向 $r")"

echo "權限"
check "作者不能進入編輯頁面" "403" "$(code alice editor/index.php)"
check "編輯不能進入作者頁面" "403" "$(code editor author/index.php)"
check "未登入會導回登入頁" "302" "$(code nobody author/index.php)"
check "作者看不到別人的稿件" "302" "$(code bob 'author/view.php?id=1')"
check "編輯看不到作者草稿" "302" "$(code editor 'editor/review.php?id=1')"

echo "非法狀態轉換"
post alice author/delete.php -d id=2 >/dev/null
check "不能刪除審查中的稿件" "alert-error" "$(flash alice author/index.php)"
post bob author/delete.php -d id=1 >/dev/null
check "不能刪除別人的稿件" "alert-error" "$(flash bob author/index.php)"
post bob author/submit.php -d id=1 >/dev/null
check "不能送出別人的稿件" "alert-error" "$(flash bob author/index.php)"
check "不能編輯審查中的稿件" "302" "$(code alice 'author/edit.php?id=2')"
check "作者不能審查稿件" "403" "$(post alice editor/review.php -d id=2 -d decision=approve)"
check "缺少 CSRF token 會被拒絕" "400" "$(curl -s -b "$TMP/alice" -o /dev/null -w '%{http_code}' -d id=1 "$BASE/author/submit.php")"
check "GET 不能修改資料" "405" "$(code alice 'author/submit.php?id=1')"

echo "完整流程（稿件 #1）"
post alice author/submit.php -d id=1 >/dev/null
check "作者送出：編輯中 → 審查中" "審查中" "$(flash alice author/index.php)"
post editor editor/review.php -d id=1 -d decision=reject --data-urlencode "comment=請補充資料" >/dev/null
check "編輯退回：審查中 → 編輯中" "編輯中" "$(flash editor editor/index.php)"
check "作者看得到退回意見" "請補充資料" "$(curl -s -b "$TMP/alice" "$BASE/author/edit.php?id=1")"
post alice author/submit.php -d id=1 >/dev/null
post editor editor/review.php -d id=1 -d decision=approve >/dev/null
check "編輯通過：審查中 → 已通過" "已通過" "$(flash editor editor/index.php)"
post editor editor/review.php -d id=1 -d decision=reject >/dev/null
check "已通過的稿件不能再退回" "alert-error" "$(flash editor editor/index.php)"

echo
echo "通過 $PASS 項，失敗 $FAIL 項"
[[ $FAIL -eq 0 ]]
