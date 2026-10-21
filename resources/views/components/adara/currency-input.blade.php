@props(['id', 'label', 'name' => null, 'hint' => 'Ingresá un importe en pesos, con coma para los centavos.', 'error' => null, 'required' => false])

<x-adara.input :id="$id" :name="$name" :label="$label" :hint="$hint" :error="$error" :required="$required" prefix="$" type="text" inputmode="decimal" autocomplete="off" {{ $attributes }} />
