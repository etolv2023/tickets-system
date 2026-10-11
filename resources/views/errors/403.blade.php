@extends('errors.layout')

@section('code', '403')
@section('heading', __('غير مسموح'))
@section('message', __($exception->getMessage() === '' || $exception->getMessage() === 'This action is unauthorized.' ? 'ليس لديك صلاحية للوصول إلى هذه الصفحة.' : $exception->getMessage()))
