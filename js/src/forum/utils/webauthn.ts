export function decodeUrlSafe(base64url: string): ArrayBuffer {
  const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
  const pad = base64.length % 4;
  const padded = pad ? base64 + '='.repeat(4 - pad) : base64;
  const binary = atob(padded);
  const bytes = new Uint8Array(binary.length);

  for (let i = 0; i < binary.length; i++) {
    bytes[i] = binary.charCodeAt(i);
  }

  return bytes.buffer;
}

export function encodeUrlSafe(buffer: ArrayBuffer): string {
  const bytes = new Uint8Array(buffer);
  let binary = '';

  for (let i = 0; i < bytes.byteLength; i++) {
    binary += String.fromCharCode(bytes[i]);
  }

  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

export function decodeCreationOptions(publicKey: any): any {
  const options = { ...publicKey };
  options.challenge = decodeUrlSafe(options.challenge);
  options.user = { ...options.user, id: decodeUrlSafe(options.user.id) };
  options.excludeCredentials = (options.excludeCredentials || []).map((credential: any) => ({
    ...credential,
    id: decodeUrlSafe(credential.id),
  }));

  return options;
}

export function decodeRequestOptions(publicKey: any): any {
  const options = { ...publicKey };
  options.challenge = decodeUrlSafe(options.challenge);
  options.allowCredentials = (options.allowCredentials || []).map((credential: any) => ({
    ...credential,
    id: decodeUrlSafe(credential.id),
  }));

  return options;
}

export function serializeRegistrationCredential(credential: PublicKeyCredential): any {
  const response = credential.response as AuthenticatorAttestationResponse;

  return {
    id: credential.id,
    rawId: encodeUrlSafe(credential.rawId),
    type: credential.type,
    response: {
      attestationObject: encodeUrlSafe(response.attestationObject),
      clientDataJSON: encodeUrlSafe(response.clientDataJSON),
      transports: response.getTransports ? response.getTransports() : undefined,
    },
  };
}

export function serializeAssertionCredential(credential: PublicKeyCredential): any {
  const response = credential.response as AuthenticatorAssertionResponse;

  return {
    id: credential.id,
    rawId: encodeUrlSafe(credential.rawId),
    type: credential.type,
    response: {
      authenticatorData: encodeUrlSafe(response.authenticatorData),
      clientDataJSON: encodeUrlSafe(response.clientDataJSON),
      signature: encodeUrlSafe(response.signature),
      userHandle: response.userHandle ? encodeUrlSafe(response.userHandle) : undefined,
    },
  };
}

export function isUserCancellation(error: unknown): boolean {
  return error instanceof DOMException && (error.name === 'NotAllowedError' || error.name === 'AbortError');
}
