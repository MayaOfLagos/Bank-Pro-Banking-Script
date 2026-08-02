<template>
  <TransferStep
    endpoint="/api/user/transfer-verify-imf.php"
    field-name="imf_code"
    title="IMF Code"
    subtitle="IMF clearance verification"
    description="Enter your IMF clearance code. After this step your OTP will be sent."
    input-label="IMF Code"
    submit-label="Verify IMF Code"
    step-label="Step 3 of 3"
    placeholder="Enter your IMF code"
    kind="short"
  />
</template>

<script setup>
import TransferStep from '../components/transfer/TransferStep.vue'
</script>
