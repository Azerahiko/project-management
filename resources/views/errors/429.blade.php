@extends('errors.layout')

@section('title', 'Terlalu Banyak Permintaan')
@section('code', '429 - Too Many Requests')
@section('header', 'Batas Percobaan Terlampaui')
@section('message', $exception->getMessage() ?: 'Terlalu banyak percobaan aktivasi atau masuk. Demi keamanan sistem, akses dibatasi sementara. Silakan coba kembali beberapa saat lagi.')
