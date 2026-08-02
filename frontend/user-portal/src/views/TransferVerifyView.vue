<template>
  <TransferStep
    endpoint="/api/user/transfer-verify-pin.php"
    field-name="pin"
    title="Enter OTP"
    subtitle="One-time password sent to your email"
    description="An OTP has been sent to your registered email address. Enter it below to complete your transfer."
    input-label="OTP Code"
    submit-label="Verify OTP"
    step-label="Final step"
    placeholder="••••••"
    kind="otp"
  />
</template>

<script setup>
import TransferStep from '../components/transfer/TransferStep.vue'
</script>
