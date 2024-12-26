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

### 최초 작성 코드로 테스트

1000개마다 로그 기록

```shell
16.402807 // app/Console/Commands/test.php:164
33.538999 // app/Console/Commands/test.php:164
50.352262 // app/Console/Commands/test.php:164
67.480664 // app/Console/Commands/test.php:164
84.477739 // app/Console/Commands/test.php:164
101.426085 // app/Console/Commands/test.php:164
118.400739 // app/Console/Commands/test.php:164
135.56749 // app/Console/Commands/test.php:164
152.732678 // app/Console/Commands/test.php:164
169.912189 // app/Console/Commands/test.php:164
187.632261 // app/Console/Commands/test.php:164
205.805444 // app/Console/Commands/test.php:164
223.101575 // app/Console/Commands/test.php:164
240.752262 // app/Console/Commands/test.php:164
257.799721 // app/Console/Commands/test.php:164
275.084833 // app/Console/Commands/test.php:164
292.521097 // app/Console/Commands/test.php:164
309.732434 // app/Console/Commands/test.php:164
```

### 요구사항 재확인 후 테스트

-   해당 테스트에서는 일부 항목을 가져올 때 EQPID의 탐색이 누락되어있음을 확인했습니다.

```shell
"total time : 1274.8751"
```

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
