# 실습 과제 - Day4

## 설치법

1. `.env` 설정
2. `\App\Services\Connections\MdwConnection` 설정
3. `composer update` 실행
4. `php artisan migrate` 실행

## 커맨드

### 1. `php artisan orange:query-only`

-   모든 데이터를 가장 단순한 방법으로 생성하는 커맨드 (값이 필요할 때 마다 쿼리를 통해서 호출)

### 2. `php artisan orange:with-cache`

-   일부 데이터를 미리 가져온 상태에서 컬렉션에서 탐색하는 커맨드

### 2. `php artisan orange:optimized`

-   가장 최적화 된 방법으로 수행하는 커맨드

## 테스트 기록

### orange:query-only

```shell
"total time : 5577.486758"
```

### orange:optimized

```shell
"total time : 309.774007"
```

### orange:with-cache

```shell
"total time : 641.437134"
```

### 인덱스된 다른 db에서 orange:query-only와 동일한 로직을 실행할경우

```shell
"total time : 447.259331"
```
